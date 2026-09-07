<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/DiceUT.php";

/**
 * Every flow in the game that rolls a die, pinned at the point where the roll happens: which
 * globals the roll writes, which state the flow lands in, and what it leaves on the benefit stack.
 *
 * This is the guard for the die roll provenance seam of the Fantasies and Futures work
 * (misc/FF_PLAN.md, Illuminati): the seam moves interruptBenefit() ahead of the roll in several
 * flows so a civ row queued by the roll resolves before the roller's own continuation, and none of
 * it may change what a table without such a civ does.
 */
final class DiceTest extends TestCase {
    private DiceUT $game;

    protected function setUp(): void {
        $this->game = new DiceUT();
        $this->game->init();
    }

    // ------------------------------------------------------------ die faces

    function testBlackDieRemapsSixthFaceToOne() {
        $this->game->seedRand(5);
        $this->assertEquals(1, $this->game->rollBlackConquerDie(DiceUT::ROLLER, false));
        $this->assertEquals(1, $this->game->getGameStateValue("conquer_die_black"));
    }

    function testRedDieRemapsSixthFaceToTwo() {
        $this->game->seedRand(5);
        $this->assertEquals(2, $this->game->rollRedConquerDie(DiceUT::ROLLER, false));
        $this->assertEquals(2, $this->game->getGameStateValue("conquer_die_red"));
    }

    function testConquerDiceConsumeRedThenBlack() {
        $this->game->seedRand(3, 4);
        $this->game->rollConquerDice(DiceUT::ROLLER);

        $this->assertEquals(3, $this->game->getGameStateValue("conquer_die_red"));
        $this->assertEquals(4, $this->game->getGameStateValue("conquer_die_black"));
    }

    function testConquerDiceRemapBothSixthFaces() {
        $this->game->seedRand(5, 5);
        $this->game->rollConquerDice(DiceUT::ROLLER);

        $this->assertEquals(2, $this->game->getGameStateValue("conquer_die_red"));
        $this->assertEquals(1, $this->game->getGameStateValue("conquer_die_black"));
    }

    function testScienceDieWritesTheNamedGlobal() {
        $this->game->seedRand(3);
        $this->assertEquals(3, $this->game->rollScienceDie("", "science_die_empiricism", DiceUT::ROLLER, false));
        $this->assertEquals(3, $this->game->getGameStateValue("science_die_empiricism"));
        $this->assertEquals(0, $this->game->getGameStateValue("science_die"));
    }

    // ------------------------------------------------------------- research

    function testResearchRollsAndOpensTheResearchState() {
        $this->game->seedRand(3);
        $this->game->queueBenefitNormal(BE_RESEARCH, DiceUT::ROLLER);
        $this->game->runManager();

        $this->assertEquals("research", $this->game->stateName());
        $this->assertEquals(3, $this->game->getGameStateValue("science_die"));
        $this->assertEquals(0, $this->game->getGameStateValue("science_die_empiricism"));
    }

    function testResearchWithEmpiricismRollsTwice() {
        $this->game->tapestries = [TAP_EMPIRICISM];
        $this->game->seedRand(2, 4);
        $this->game->queueBenefitNormal(BE_RESEARCH, DiceUT::ROLLER);
        $this->game->runManager();

        $this->assertEquals("research", $this->game->stateName());
        $this->assertEquals(2, $this->game->getGameStateValue("science_die"));
        $this->assertEquals(4, $this->game->getGameStateValue("science_die_empiricism"));
    }

    /**
     * action_research_decision takes the advance flags off whatever row is on top of the stack when
     * the state opens, so each of the three research rows has to leave its own flags there.
     */
    function testResearchLeavesItsOwnAdvanceFlagsOnTheStack() {
        $expected = [
            BE_RESEARCH => FLAG_GAIN_BENEFIT | FLAG_PAY_BONUS,
            BE_RESEARCH_NB => 0,
            BE_RESEARCH_MAXOUT => FLAG_MAXOUT_BONUS,
        ];
        foreach ($expected as $ben => $flags) {
            $game = new DiceUT();
            $game->init();
            $game->seedRand(2);
            $game->queueBenefitNormal($ben, DiceUT::ROLLER);
            $game->runManager();

            $this->assertEquals("research", $game->stateName(), "row $ben");
            $this->assertEquals($flags, $game->pendingFlags(), "row $ben");
        }
    }

    // -------------------------------------------------------- optional rows

    /** Rows 84-91 and 97-100 are the research state entered with a track already decided. */
    function testOptionalAdvanceRowOpensResearchWithItsOwnTrack() {
        $this->assertFalse($this->game->resolveRow(BE_ADVANCE_SCIENCE_NOBENEFIT_OPT));

        $this->assertEquals("research", $this->game->stateName());
        $this->assertEquals(2, $this->game->getGameStateValue("science_die"));
        $this->assertEquals(0, $this->game->pendingFlags());
    }

    // -------------------------------------------------- rows that roll dice

    /** 301: roll the black die twice, then choose one of the two benefits. */
    function testRow301QueuesAChoiceOfBothFaces() {
        $this->game->seedRand(3, 4);
        $this->assertFalse($this->game->resolveRow(301));

        $this->assertEquals(["o,3,4"], $this->game->benefitLabels());
    }

    /** 303: roll both conquer dice, gain both benefits, red first. */
    function testRow303QueuesRedThenBlack() {
        $this->game->seedRand(2, 3);
        $this->assertFalse($this->game->resolveRow(303));

        // red face 2 is VP per controlled territory, black face 3 is food
        $this->assertEquals(["" . BE_VP_TERRITORY, "" . BE_GAIN_FOOD], $this->game->benefitLabels());
    }

    /** 324: roll the black die, then the row that spends it, newest first. */
    function testRow324QueuesTheSpendRowAfterTheRoll() {
        $this->game->seedRand(3);
        $this->assertTrue($this->game->resolveRow(324));

        $this->assertEquals(3, $this->game->getGameStateValue("conquer_die_black"));
        $this->assertEquals(["330"], $this->game->benefitLabels());
    }

    function testRow324WithCountQueuesTheRepeat() {
        $this->game->seedRand(3);
        $this->assertTrue($this->game->resolveRow(324, 2));

        $this->assertEquals(["o,330,202", "603", "324"], $this->game->benefitLabels());
    }

    /** 325: same shape on the science die. */
    function testRow325QueuesTheSpendRowAfterTheRoll() {
        $this->game->seedRand(2);
        $this->assertTrue($this->game->resolveRow(325));

        $this->assertEquals(2, $this->game->getGameStateValue("science_die"));
        $this->assertEquals(["332"], $this->game->benefitLabels());
    }

    // --------------------------------------------------- age of discovery

    /** The roller advances with benefits, everyone else gets the no benefit advance. */
    function testAgeOfDiscoveryQueuesRollerThenOpponents() {
        $this->game->seedRand(3);
        $this->game->effect_ageOfDiscovery(DiceUT::ROLLER);

        $this->assertEquals(3, $this->game->getGameStateValue("science_die"));
        $this->assertEquals(
            [[DiceUT::ROLLER, 21 + 3], [DiceUT::OTHER, 75 + 3]],
            array_map(fn($row) => [(int) $row["benefit_player_id"], (int) $row["benefit_type"]], $this->game->benefitQueue())
        );
    }
}
