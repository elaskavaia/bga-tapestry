<?php

declare(strict_types=1);

use Bga\GameFramework\UserException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/TrackMoveUT.php";

/** The track move benefits awardBenefits hands to trackMovementInteractive. */
final class TrackMovementTest extends TestCase {
    private TrackMoveUT $game;

    protected function setUp(): void {
        $this->game = new TrackMoveUT();
        $this->game->init();
        $this->game->setGameStateValue("cube_choice", -1);
    }

    /** Exploration benefit ids (material constants are not defined yet when a data provider runs) */
    static function moves(): array {
        return [
            "advance" => [22, 1, false],
            "regress" => [67, -1, false],
            "advance no benefit" => [76, 1, false],
            "advance benefit no bonus" => [80, 1, false],
            "advance free bonus" => [92, 1, false],
            "jump" => [151, 4, false],
            "advance maxout" => [156, 1, false],
            "advance benefit free bonus" => [182, 1, false],
            "regress no benefit" => [131, -1, true],
        ];
    }

    #[DataProvider("moves")]
    function testTheOnlyTokenMoves(int $ben, int $change, bool $mandatory) {
        $game = $this->game;
        $game->addCubeAt(DiceUT::ROLLER, "tech_spot_" . TRACK_EXPLORATION . "_3");

        $this->assertTrue($game->resolveRow($ben));

        $this->assertEquals([[TRACK_EXPLORATION, 3, $change, DiceUT::ROLLER]], $game->moved);
        $this->assertEquals([[(int) $game->benefit_types[$ben]["flags"], $mandatory]], $game->movedWith);
    }

    /** BGA #58486: a token removed by technology 12 leaves no token to move, so the move is void (FORMAL_RULES MOVE.2, MOVE.3). */
    #[DataProvider("moves")]
    function testNoTokenOnTheTrackVoidsTheMove(int $ben) {
        $game = $this->game;
        $game->addCubeAt(DiceUT::ROLLER, "tech_spot_" . TRACK_SCIENCE . "_3");

        $this->assertTrue($game->resolveRow($ben));

        $this->assertEquals([], $game->moved);
        $this->assertEquals([], $game->benefitQueue());
        $this->assertNotEmpty($game->notificationLike("has no player token"));
    }

    /** BGA #58486: a void pick from benefitOption must not leave its token choice to a later advance. */
    function testVoidChoiceClearsTheTokenChoice() {
        $game = $this->game;
        $game->addCubeAt(DiceUT::ROLLER, "tech_spot_" . TRACK_SCIENCE . "_3");
        $game->benefitSingleEntry("o," . BE_ADVANCE_T . "," . BE_GAIN_COIN, 0, DiceUT::ROLLER);
        $game->gamestate->jumpToState(19);

        $game->action_choose_benefit(BE_ADVANCE_T, 4);
        $game->gamestate->jumpToState(18);
        $game->runManager();

        $this->assertEquals([], $game->moved);
        $this->assertEquals(-1, $game->getGameStateValue("cube_choice"));
    }

    function testTheocracyBlocksTheAdvance() {
        $game = $this->game;
        $game->tapestries = [TAP_THEOCRACY];
        $game->addCubeAt(DiceUT::ROLLER, "tech_spot_" . TRACK_SCIENCE . "_3");
        $game->setGameStateValue("cube_choice", 3);

        $this->assertTrue($game->resolveRow(BE_ADVANCE_S));

        $this->assertEquals([], $game->moved);
        $this->assertNotEmpty($game->notificationLike("THEOCRACY"));
        $this->assertEquals(-1, $game->getGameStateValue("cube_choice"));
    }

    function testOpponentsDictatorshipRefusesACheckedAdvance() {
        $game = $this->game;
        $turn = $game->getPlayerTurn(DiceUT::OTHER);
        $game->dbAddStructure(DiceUT::OTHER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_EXPLORATION . "_4", "dic_{$turn}_1");

        $this->expectException(UserException::class);
        $this->expectExceptionMessage("DICTATORSHIP");
        $game->triggerAdvanceCheck(DiceUT::ROLLER, TRACK_EXPLORATION, true);
    }

    /** Completing technology leaves a virtual cube on its last space, which still counts as a token (max-out bonus). */
    function testVirtualCubeIsATokenOnTheTrack() {
        $game = $this->game;
        $game->addCubeAt(DiceUT::ROLLER, "tech_spot_" . TRACK_TECHNOLOGY . "_12", CUBE_AI);

        $this->assertTrue($game->resolveRow(159));

        $this->assertEquals([[TRACK_TECHNOLOGY, 12, ACTION_ADVANCE, DiceUT::ROLLER]], $game->moved);
    }

    /** BGA #58486 (comment by ukcody34, table 910048653): AGE OF DISCOVERY rolls technology, where the opponent has no token. */
    function testAgeOfDiscoveryOpponentWithNoTokenOnTheRolledTrackIsVoid() {
        $game = $this->game;
        $game->addCubeAt(DiceUT::ROLLER, "tech_spot_" . TRACK_TECHNOLOGY . "_3");
        $game->addCubeAt(DiceUT::OTHER, "tech_spot_" . TRACK_EXPLORATION . "_0");
        $game->addCubeAt(DiceUT::OTHER, "tech_spot_" . TRACK_SCIENCE . "_3");
        $game->seedRand(TRACK_TECHNOLOGY);

        $game->effect_ageOfDiscovery(DiceUT::ROLLER);
        $game->runManager();

        $this->assertEquals([[TRACK_TECHNOLOGY, 3, ACTION_ADVANCE, DiceUT::ROLLER]], $game->moved);
        $this->assertEquals([], $game->benefitQueue());
        $this->assertEquals(DiceUT::OTHER, $game->notificationLike("has no player token")["args"]["player_id"]);
    }
}
