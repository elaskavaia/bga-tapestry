<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Composite benefits ("o," in benefitOption, "a," in benefitChoice) driven through the real
 * action_choose_benefit / action_first_benefit and the real track move, one token per track.
 * Two tokens on one track are covered by TwoTokensOnTrackTest.
 */
final class BenefitChoiceTest extends TestCase {
    const PLAYER = 1;

    private GameUT $game;
    /** token ids keyed by track */
    private array $tokens = [];

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->setGameStateValue("cube_choice", -1);
        $this->placeToken(TRACK_EXPLORATION, 1);
        $this->placeToken(TRACK_SCIENCE, 4);
    }

    private function placeToken(int $track, int $spot): void {
        $this->tokens[$track] = (int) $this->game->addCubeAt(self::PLAYER, "tech_spot_{$track}_$spot");
    }

    private function tokenLocation(int $track): string {
        return $this->game->structureLocation($this->tokens[$track]);
    }

    private function queue(string $cat): void {
        $this->game->benefitSingleEntry($cat, 0, self::PLAYER);
    }

    /** benefitChoice, the client sends the button's destination spot, 0 for a non-track button. */
    private function takeFirst(int $ben, int $spot = 0): void {
        $this->game->gamestate->jumpToState(29);
        $this->game->action_first_benefit($ben, $spot);
    }

    /** benefitOption, same buttons as benefitChoice. */
    private function chooseOne(int $ben, int $spot = 0): void {
        $this->game->gamestate->jumpToState(19);
        $this->game->action_choose_benefit($ben, $spot);
    }

    /** What stBenefitManager does with a standard row on top of the stack. */
    private function resolve(int $ben): void {
        $this->game->gamestate->jumpToState(18);
        $this->game->resolveBenefit($ben, self::PLAYER);
    }

    /** Before e9cf09f the coin button's spot 0 was stored as the token choice and the advance asked trackSelect. */
    function testNonTrackFirstThenAdvanceMovesTheOnlyTokenWithoutAsking() {
        $this->queue("a," . BE_GAIN_COIN . "," . BE_ADVANCE_S);
        $this->takeFirst(BE_GAIN_COIN);
        $this->resolve(BE_GAIN_COIN);

        $this->resolve(BE_ADVANCE_S);

        $this->assertNotEquals("trackSelect", $this->game->getStateName());
        $this->assertEquals("tech_spot_2_5", $this->tokenLocation(TRACK_SCIENCE));
        $this->assertEquals([(string) BE_SPOT], $this->game->benefitLabels());
    }

    /** Before e9cf09f the coin button's spot 0 matched no token and the jump was silently skipped. */
    function testNonTrackFirstThenJumpMovesTheOnlyToken() {
        $this->queue("a," . BE_GAIN_COIN . ",152");
        $this->takeFirst(BE_GAIN_COIN);
        $this->resolve(BE_GAIN_COIN);

        $this->resolve(152);

        $this->assertEquals("tech_spot_2_8", $this->tokenLocation(TRACK_SCIENCE));
        $this->assertEquals([(string) BE_SPOT], $this->game->benefitLabels());
    }

    function testTrackFirstButtonAdvancesThenTheRestIsResolved() {
        $this->queue("a," . BE_GAIN_COIN . "," . BE_ADVANCE_S);
        $this->takeFirst(BE_ADVANCE_S, 5);
        $this->assertEquals([(string) BE_ADVANCE_S, (string) BE_GAIN_COIN], $this->game->benefitLabels());

        $this->resolve(BE_ADVANCE_S);

        $this->assertEquals("tech_spot_2_5", $this->tokenLocation(TRACK_SCIENCE));
        $this->assertEquals([(string) BE_SPOT, (string) BE_GAIN_COIN], $this->game->benefitLabels());
    }

    /** FUTURISTS with the adjustments: two "jump or decline" choices. */
    private function setupFuturists(): void {
        $game = $this->game;
        $this->placeToken(TRACK_MILITARY, 0);
        $this->placeToken(TRACK_TECHNOLOGY, 0);
        $game->setGameStateValue("variant_adjustments", 8);
        $game->doAdjustMaterial(2, 8);
        $game->giveCiv(self::PLAYER, CIV_FUTURISTS);
        $game->getCivilizationInstance(CIV_FUTURISTS, true)->setupCiv(self::PLAYER, "start");
    }

    function testFuturistsJumpButtonMovesTheChosenTrack() {
        $this->setupFuturists();
        $jumps = "o,151,152,153,154," . BE_DECLINE;
        $this->assertEquals([$jumps, "601", $jumps, (string) BE_CONFIRM], $this->game->benefitLabels());

        $this->chooseOne(152, 8);
        $this->resolve(152);

        $this->assertEquals("tech_spot_2_8", $this->tokenLocation(TRACK_SCIENCE));
        $this->assertEquals("tech_spot_1_1", $this->tokenLocation(TRACK_EXPLORATION));
    }

    /**
     * Pins buggy behavior: the rule is "up to two different tracks", and 601 between the two
     * choices is meant to drop the track already jumped, but it is a no-op, so the second choice
     * still offers 152. Correct would be "o,151,153,154,401".
     */
    function testFuturistsSecondChoiceStillOffersTheJumpedTrack() {
        $this->setupFuturists();

        $this->chooseOne(152, 8);
        $this->resolve(152);
        $this->game->benefitCashed($this->game->getCurrentBenefit(BE_SPOT));
        $this->resolve(601);

        $this->assertEquals(["o,151,152,153,154," . BE_DECLINE, (string) BE_CONFIRM], $this->game->benefitLabels());
    }

    function testFuturistsDeclineThenJumpMovesOnlyTheSecondChoice() {
        $this->setupFuturists();

        $this->chooseOne(BE_DECLINE);
        $this->resolve(BE_DECLINE);
        $this->resolve(601);
        $this->chooseOne(153, 4);
        $this->resolve(153);

        $this->assertEquals("tech_spot_3_4", $this->tokenLocation(TRACK_MILITARY));
        $this->assertEquals("tech_spot_4_0", $this->tokenLocation(TRACK_TECHNOLOGY));
        $this->assertEquals("tech_spot_2_4", $this->tokenLocation(TRACK_SCIENCE));
        $this->assertEquals([(string) BE_SPOT, (string) BE_CONFIRM], $this->game->benefitLabels());
    }

    /** The shape argAddTrackChoice gives one track option, which tapestry.js turns into spot buttons. */
    private function trackOption(int $track, int $change, int $spot): array {
        return [
            "cubes" => ["cube_" . $this->tokens[$track]],
            "spots" => ["{$track}_$spot" => 1],
            "track" => $track,
            "change" => $change,
        ];
    }

    private function argsFor(string $cat): array {
        $this->queue($cat);
        return $this->game->argBenefitChoice();
    }

    function testArgsOfAdvanceOptions() {
        $args = $this->argsFor("o," . BE_ADVANCE_E . "," . BE_ADVANCE_S);

        $this->assertEquals("or", $args["c"]);
        $this->assertEquals([BE_ADVANCE_E, BE_ADVANCE_S], $args["options"]);
        $this->assertEquals(
            [BE_ADVANCE_E => $this->trackOption(TRACK_EXPLORATION, 1, 2), BE_ADVANCE_S => $this->trackOption(TRACK_SCIENCE, 1, 5)],
            $args["tracks"]
        );
        $this->assertEquals(1, $args["tracks_change"]);
    }

    function testArgsOfRegressOptions() {
        $args = $this->argsFor("o," . BE_REGRESS_E . "," . BE_REGRESS_S);

        $this->assertEquals(
            [BE_REGRESS_E => $this->trackOption(TRACK_EXPLORATION, -1, 0), BE_REGRESS_S => $this->trackOption(TRACK_SCIENCE, -1, 3)],
            $args["tracks"]
        );
        $this->assertEquals(-1, $args["tracks_change"]);
    }

    function testArgsOfJumpOptions() {
        $args = $this->argsFor("o,151,152");

        $this->assertEquals(
            [151 => $this->trackOption(TRACK_EXPLORATION, 4, 5), 152 => $this->trackOption(TRACK_SCIENCE, 4, 8)],
            $args["tracks"]
        );
        $this->assertEquals(4, $args["tracks_change"]);
    }

    /** Only the track option gets spots; the coin is a plain button. */
    function testArgsOfMixedChoice() {
        $args = $this->argsFor("a," . BE_GAIN_COIN . "," . BE_ADVANCE_S);

        $this->assertEquals("choice", $args["c"]);
        $this->assertEquals([BE_GAIN_COIN, BE_ADVANCE_S], $args["options"]);
        $this->assertEquals([BE_ADVANCE_S => $this->trackOption(TRACK_SCIENCE, 1, 5)], $args["tracks"]);
        $this->assertEquals(1, $args["tracks_change"]);
    }

    function testArgsOfNonTrackOptionsHaveNoTracks() {
        $args = $this->argsFor("o," . BE_GAIN_COIN . "," . BE_GAIN_WORKER);

        $this->assertEquals([BE_GAIN_COIN, BE_GAIN_WORKER], $args["options"]);
        $this->assertArrayNotHasKey("tracks", $args);
        $this->assertArrayNotHasKey("tracks_change", $args);
    }
}
