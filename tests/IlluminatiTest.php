<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/DiceUT.php";

/**
 * Illuminati, the seventh Fantasies and Futures civilization: three dice on the mat, an opponent's
 * roll takes one off it and pays the owner what the roll produced, and income turns 2-5 score 6 VP
 * per die still there before putting all three back.
 *
 * DiceUT::ROLLER is the opponent doing the rolling and DiceUT::OTHER owns the mat, so every case
 * here is the cross-player one the card is about.
 */
class IlluminatiUT extends DiceUT {
    const OWNER = self::OTHER;

    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->eras[self::ROLLER] = 2;
        $this->eras[self::OWNER] = 2;
        $this->giveCiv(self::OWNER, CIV_ILLUMINATI);
        $this->getCivilizationInstance(CIV_ILLUMINATI, true); // includes civs/Illuminati.php
    }

    function illuminati(): Illuminati {
        return $this->getCivilizationInstance(CIV_ILLUMINATI, true);
    }

    function mask(): int {
        return (int) $this->getGameStateValue(Illuminati::GLOBAL_DICE);
    }

    function setMask(int $mask): void {
        $this->setGameStateValue(Illuminati::GLOBAL_DICE, $mask);
    }

    /** Cards for awardCard to draw; with an empty deck the draw is announced as void instead. */
    function fillDeck(int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $this->addCard(CARD_TAPESTRY, "deck_tapestry", 0, TAP_ACADEMIA);
        }
    }

    /** The income row and the benefit manager resolving it, the way a real income turn reaches it. */
    function useIncomeAbility(int $turn): void {
        $this->startIncomeTurn(self::OWNER, $turn);
        $this->queueEraCivAbility(CIV_ILLUMINATI, self::OWNER, $turn);
        $this->runManager();
    }

    /** [player_id, label] of every pending row in pop order; a composite row shows its category. */
    function rows(): array {
        return array_map(
            fn($row) => [
                (int) $row["benefit_player_id"],
                $row["benefit_category"] == "standard" ? (int) $row["benefit_type"] : $row["benefit_category"],
            ],
            $this->benefitQueue()
        );
    }
}

final class IlluminatiTest extends TestCase {
    private IlluminatiUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): IlluminatiUT {
        $game = new IlluminatiUT($players);
        $game->init();
        $game->doAdjustMaterial($players, 8);
        $game->setMask(Illuminati::ALL_DICE);
        return $game;
    }

    // ------------------------------------------------------------- material

    function testMaterialEntry() {
        $info = $this->game->civilizations[CIV_ILLUMINATI];

        $this->assertEquals("FF", $info["exp"]);
        $this->assertFalse($info["automa"]);
        $this->assertEquals(["from" => 2, "to" => 5, "decline" => false], $info["income_trigger"]);
        $this->assertArrayNotHasKey("slots", $info);
    }

    // ---------------------------------------------------------------- setup

    function testSetupDrawsThreeTapestryCardsAndFillsTheMat() {
        $game = $this->newGame();
        $game->setMask(0);
        $game->fillDeck(5);

        $game->setupCiv(CIV_ILLUMINATI, IlluminatiUT::OWNER, true);

        $this->assertEquals(Illuminati::ALL_DICE, $game->mask());
        $this->assertEquals([[IlluminatiUT::OWNER, BE_ILLUMINATI_DRAW]], $game->rows());
    }

    /** Gaining the civ mid game runs the same code, there is no separate mid game entry. */
    function testMidGameSetupIsTheSame() {
        $game = $this->newGame();
        $game->setMask(0);
        $game->fillDeck(5);

        $game->setupCiv(CIV_ILLUMINATI, IlluminatiUT::OWNER, false);

        $this->assertEquals(Illuminati::ALL_DICE, $game->mask());
        $this->assertEquals([[IlluminatiUT::OWNER, BE_ILLUMINATI_DRAW]], $game->rows());
    }

    function testTheDrawRowOffersThreeCardsAndKeepsOne() {
        $game = $this->newGame();
        $game->fillDeck(5);
        $game->gamestate->changeActivePlayer(IlluminatiUT::OWNER);
        $game->queueBenefitNormal(BE_ILLUMINATI_DRAW, IlluminatiUT::OWNER);
        $game->runManager();

        $this->assertEquals("keepCard", $game->stateName());
        $drawn = array_keys($game->getCardsSearch(CARD_TAPESTRY, null, "draw", IlluminatiUT::OWNER));
        $this->assertCount(3, $drawn);

        $game->effect_keepCard([$drawn[0]], IlluminatiUT::OWNER, $game->getCurrentBenefitWithInfo());

        $this->assertEquals([$drawn[0]], array_keys($game->getCardsInHand(IlluminatiUT::OWNER, CARD_TAPESTRY)));
        $this->assertCount(2, $game->getCardsSearch(CARD_TAPESTRY, null, "discard"));
    }

    // ------------------------------------------------------- the conquer dice

    function testAnOpponentsConquerTakesBothDiceAndPaysAheadOfTheDiePick() {
        $game = $this->game;
        $game->dbAddStructure(IlluminatiUT::ROLLER, BUILDING_OUTPOST, 0, "hand");
        $game->seedRand(2, 3);
        $game->effect_conquer(IlluminatiUT::ROLLER, "1_1", ["1_1"], false, null, "");

        $this->assertEquals(Illuminati::DICE["science"], $game->mask());
        // red face 2 is VP per controlled territory, black face 3 is food, then the roller's die pick
        $this->assertEquals(
            [[IlluminatiUT::OWNER, BE_VP_TERRITORY], [IlluminatiUT::OWNER, BE_GAIN_FOOD], [IlluminatiUT::ROLLER, 141]],
            $game->rows()
        );
    }

    /** Black face 1 is the benefit of the territory the roller is conquering. */
    function testBlackFaceOnePaysTheTerritoryBenefit() {
        $game = $this->game;
        $game->tileBenefit = [BE_GAIN_CULTURE];
        $game->seedRand(1);
        $game->rollBlackConquerDie(IlluminatiUT::ROLLER, false);

        $this->assertEquals(Illuminati::ALL_DICE & ~Illuminati::DICE["black"], $game->mask());
        $this->assertEquals([[IlluminatiUT::OWNER, BE_GAIN_CULTURE]], $game->rows());
    }

    /** Black face 1 on a territory with no benefit takes the die but pays nothing. */
    function testAZeroFacePaysNothing() {
        $game = $this->game;
        $game->tileBenefit = [];
        $game->seedRand(1);
        $game->rollBlackConquerDie(IlluminatiUT::ROLLER, false);

        $this->assertEquals(Illuminati::ALL_DICE & ~Illuminati::DICE["black"], $game->mask());
        $this->assertEquals([], $game->rows());
        $this->assertNotEmpty($game->notificationLike("gains nothing"));
    }

    /** Row 301 rolls the black die twice: the die is gone after the first roll, so only it pays. */
    function testARerollPaysOnlyForTheFirstRoll() {
        $game = $this->game;
        $game->seedRand(3, 4);
        $game->resolveRow(301);

        // the owner is paid for face 3 only, the roller still chooses between both faces
        $this->assertEquals([[IlluminatiUT::OWNER, BE_GAIN_FOOD], [IlluminatiUT::ROLLER, "o,3,4"]], $game->rows());
    }

    // ------------------------------------------------------- the science die

    /**
     * The owner's optional advance is rows 76-79 plus decline, not 84-87: those write the dice
     * globals the roller's research decision is about to read.
     */
    function testAnOpponentsResearchPaysAnOptionalAdvanceAheadOfTheirDecision() {
        $game = $this->game;
        $game->seedRand(2);
        $game->resolveRow(BE_RESEARCH);

        $this->assertEquals(Illuminati::ALL_DICE & ~Illuminati::DICE["science"], $game->mask());
        $this->assertEquals(
            [[IlluminatiUT::OWNER, $this->advanceChoice(2)], [IlluminatiUT::ROLLER, BE_ADVANCE_SCIENCE_BENEFIT_OPT]],
            $game->rows()
        );
    }

    /** Empiricism's second roll finds the die already gone, and the roller still gets both tracks. */
    function testEmpiricismSecondRollPaysNothingAndKeepsBothTracks() {
        $game = $this->game;
        $game->tapestries = [TAP_EMPIRICISM];
        $game->seedRand(2, 4);
        $game->resolveRow(BE_RESEARCH);

        $this->assertEquals(
            [[IlluminatiUT::OWNER, $this->advanceChoice(2)], [IlluminatiUT::ROLLER, BE_ADVANCE_SCIENCE_BENEFIT_OPT]],
            $game->rows()
        );
        $this->assertEquals(2, $game->getGameStateValue("science_die"));
        $this->assertEquals(4, $game->getGameStateValue("science_die_empiricism"));
    }

    function testAgeOfDiscoveryOrdersOwnerThenRollerThenTheOthers() {
        $game = $this->newGame(3);
        $game->seedRand(3);
        $game->effect_ageOfDiscovery(IlluminatiUT::ROLLER);

        $this->assertEquals(
            [[IlluminatiUT::OWNER, $this->advanceChoice(3)], [IlluminatiUT::ROLLER, 21 + 3], [IlluminatiUT::OWNER, 75 + 3], [3, 75 + 3]],
            $game->rows()
        );
    }

    function testRow325PaysTheOwnerBeforeTheRollerSpendsTheDie() {
        $game = $this->game;
        $game->seedRand(2);
        $game->resolveRow(325);

        $this->assertEquals([[IlluminatiUT::OWNER, $this->advanceChoice(2)], [IlluminatiUT::ROLLER, 332]], $game->rows());
    }

    // ---------------------------------------------------- the owner's own rolls

    function testTheOwnerRollingLeavesEveryDieOnTheMat() {
        $game = $this->game;
        $game->seedRand(3, 4, 2);
        $game->rollConquerDice(IlluminatiUT::OWNER);
        $game->rollScienceDie("", "science_die", IlluminatiUT::OWNER, false);

        $this->assertEquals(Illuminati::ALL_DICE, $game->mask());
        $this->assertEquals([], $game->rows());
    }

    function testADieAlreadyOffTheMatIsNotTakenAgain() {
        $game = $this->game;
        $game->setMask(Illuminati::ALL_DICE & ~Illuminati::DICE["red"]);
        $game->seedRand(2);
        $game->rollRedConquerDie(IlluminatiUT::ROLLER, false);

        $this->assertEquals(Illuminati::ALL_DICE & ~Illuminati::DICE["red"], $game->mask());
        $this->assertEquals([], $game->rows());
    }

    // ------------------------------------------------------- the income turns

    function testIncomeScoresSixVPPerDieAndRefillsTheMat() {
        foreach ([Illuminati::ALL_DICE => 18, 3 => 12, 1 => 6, 0 => 0] as $mask => $vp) {
            $game = $this->newGame();
            $game->setMask($mask);
            $game->useIncomeAbility(2);

            $this->assertEquals($vp, $game->dbGetScore(IlluminatiUT::OWNER), "mask $mask");
            $this->assertEquals(Illuminati::ALL_DICE, $game->mask(), "mask $mask");
        }
    }

    function testIncomeTurnOneDoesNothing() {
        $game = $this->game;
        $game->setMask(Illuminati::DICE["red"]);
        $game->useIncomeAbility(1);

        $this->assertEquals(0, $game->dbGetScore(IlluminatiUT::OWNER));
        $this->assertEquals(Illuminati::DICE["red"], $game->mask());
        $this->assertEquals([], $game->rows());
    }

    // ------------------------------------------------------ absent or finished

    function testAFinishedOwnerLosesTheDieAndGainsNothing() {
        $game = $this->game;
        $game->eras[IlluminatiUT::OWNER] = 6;
        $game->seedRand(3);
        $game->rollBlackConquerDie(IlluminatiUT::ROLLER, false);

        $this->assertEquals(Illuminati::ALL_DICE & ~Illuminati::DICE["black"], $game->mask());
        $this->assertEquals([], $game->rows());
    }

    function testAZombieOwnerGainsNothing() {
        $game = $this->game;
        $game->_setPlayerBasicInfo([IlluminatiUT::ROLLER => [], IlluminatiUT::OWNER => ["player_zombie" => 1]]);
        $game->seedRand(3);
        $game->rollBlackConquerDie(IlluminatiUT::ROLLER, false);

        // the die leaves the mat all the same, and a zombie owner's dice never return
        $this->assertEquals(Illuminati::ALL_DICE & ~Illuminati::DICE["black"], $game->mask());
        $this->assertEquals([], $game->rows());
    }

    /**
     * An ALCHEMISTS bust queues the roller's consolation benefit off the same science roll that
     * takes the die, so its interrupt has to sit ahead of the roll like every other flow.
     */
    function testAnAlchemistsBustResolvesAfterTheOwnersGain() {
        $game = $this->game;
        $game->setGameStateValue("variant_adjustments", 2);
        $game->giveCiv(IlluminatiUT::ROLLER, CIV_ALCHEMISTS);
        $game->addCubeAt(IlluminatiUT::ROLLER, "civ_" . CIV_ALCHEMISTS . "_3");
        $game->seedRand(3); // the science die lands on the cube's slot, so the roller busts

        $game->getCivilizationInstance(CIV_ALCHEMISTS, true)->alchemistRoll(IlluminatiUT::ROLLER);

        $this->assertEquals([[IlluminatiUT::OWNER, $this->advanceChoice(3)], [IlluminatiUT::ROLLER, BE_ANYRES]], $game->rows());
    }

    function testGetAllDatasCarriesTheMaskAndTheOwner() {
        $game = $this->game;
        $game->setMask(Illuminati::DICE["science"]);

        $dice = $game->getAllDatas()["dice"];

        $this->assertEquals(Illuminati::DICE["science"], (int) $dice["on_mat"]);
        $this->assertEquals(IlluminatiUT::OWNER, (int) $dice["mat_owner"]);
    }

    function testWithoutTheCivNoDieEverLeavesTheMat() {
        $game = new DiceUT();
        $game->init();
        $game->seedRand(3);
        $game->rollBlackConquerDie(DiceUT::ROLLER, false);

        $this->assertEquals(0, (int) $game->getGameStateValue(Illuminati::GLOBAL_DICE));
        $this->assertEquals([], $game->benefitQueue());
    }

    /** The owner's science gain: advance on the rolled track with no benefit, or decline. */
    private function advanceChoice(int $track): string {
        return "o," . (BE_ADVANCE_EXPLORATION_NOBENEFIT + $track - 1) . "," . BE_DECLINE;
    }
}
