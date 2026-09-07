<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/DiceUT.php";

/**
 * A civilization that reacts to a roll, standing in for the Illuminati this seam was built for: it
 * queues one marker row for a player who is not the roller on every die it is told about. Whether
 * that row sits ahead of the roller's own continuation is the whole point of the seam.
 */
class DieProbeUT extends DiceUT {
    /** [die, face, roller_id] of every roll the hook saw. */
    public array $rolls = [];

    /** Material is included by the constructor, so this cannot be a class constant. */
    function getMarker(): int {
        return BE_GAIN_COIN;
    }

    function dieRolled(string $die, int $face, int $roller_id): void {
        parent::dieRolled($die, $face, $roller_id);
        $this->rolls[] = [$die, $face, $roller_id];
        $this->queueBenefitNormal($this->getMarker(), self::OTHER, reason("die", $die));
    }

    /** The conquer flow reads the map and the outpost pool with raw SQL, neither is modelled. */
    function getMapHexData($xcoords, $map = null) {
        return ["map_owners" => []];
    }

    function getOutpostsInHand($player_id) {
        return $this->getStructuresSearch(BUILDING_OUTPOST, null, "hand", $player_id);
    }

    function effect_placeOnMap($player_id, $structure_id, $location, $notif = "*", $ownership = true) {
        $this->structures->setLocation((int) $structure_id, $location);
    }
}

/** Records the hook instead of acting on it, to show which civilizations it is offered to. */
class RecordingCiv extends AbsCivilization {
    public static array $calls = [];

    function onDieRolled(string $die, int $face, int $roller_id): void {
        self::$calls[] = [$this->getType(), $die, $face, $roller_id];
    }
}

class CivWalkUT extends DiceUT {
    function getCivilizationInstance(int $civ, bool $strict = false): AbsCivilization {
        return new RecordingCiv($civ, $this);
    }
}

final class DieRollSeamTest extends TestCase {
    private DieProbeUT $game;

    protected function setUp(): void {
        $this->game = new DieProbeUT();
        $this->game->init();
    }

    private function mark(): string {
        return "" . $this->game->getMarker();
    }

    // ---------------------------------------------------------- rollDieFace

    function testBlackDieFaces() {
        $this->game->seedRand(0, 1, 2, 3, 4, 5);
        $faces = array_map(fn() => $this->game->rollDieFace("black"), range(1, 6));
        $this->assertEquals([0, 1, 2, 3, 4, 1], $faces);
    }

    function testRedDieFaces() {
        $this->game->seedRand(0, 1, 2, 3, 4, 5);
        $faces = array_map(fn() => $this->game->rollDieFace("red"), range(1, 6));
        $this->assertEquals([0, 1, 2, 3, 4, 2], $faces);
    }

    function testScienceDieFacesAreNotRemapped() {
        $this->game->seedRand(1, 2, 3, 4);
        $faces = array_map(fn() => $this->game->rollDieFace("science"), range(1, 4));
        $this->assertEquals([1, 2, 3, 4], $faces);
    }

    // ------------------------------------------------------------ dieRolled

    function testEachRollFunctionReportsItsDieFaceAndRoller() {
        $this->game->seedRand(5, 5, 3, 2);
        $this->game->rollConquerDice(DieProbeUT::ROLLER);
        $this->game->rollBlackConquerDie(DieProbeUT::OTHER, false);
        $this->game->rollScienceDie("", "science_die", DieProbeUT::OTHER, false);

        $this->assertEquals(
            [
                ["red", 2, DieProbeUT::ROLLER],
                ["black", 1, DieProbeUT::ROLLER],
                ["black", 3, DieProbeUT::OTHER],
                ["science", 2, DieProbeUT::OTHER],
            ],
            $this->game->rolls
        );
    }

    /** rollScienceDie defaults its player to -1, the hook has to be told who actually rolled. */
    function testScienceDieResolvesTheDefaultRollerToTheActivePlayer() {
        $this->game->seedRand(4);
        $this->game->rollScienceDie("", "science_die", -1, false);

        $this->assertEquals([["science", 4, DieProbeUT::ROLLER]], $this->game->rolls);
    }

    function testTheHookIsOfferedToEveryCivilizationInPlayNotOnlyTheRollers() {
        $game = new CivWalkUT();
        $game->init();
        $game->giveCiv(CivWalkUT::ROLLER, CIV_HERALDS);
        $game->giveCiv(CivWalkUT::OTHER, CIV_TRADERS);
        RecordingCiv::$calls = [];

        $game->seedRand(3);
        $game->rollBlackConquerDie(CivWalkUT::ROLLER, false);

        $this->assertEquals(
            [[CIV_HERALDS, "black", 3, CivWalkUT::ROLLER], [CIV_TRADERS, "black", 3, CivWalkUT::ROLLER]],
            RecordingCiv::$calls
        );
    }

    // ------------------------------------------- the roll comes before the roller acts

    function testResearchPutsTheReactingCivAheadOfTheResearchDecision() {
        $this->game->seedRand(2);
        $this->assertTrue($this->game->resolveRow(BE_RESEARCH));

        $this->assertEquals([$this->mark(), "" . BE_ADVANCE_SCIENCE_BENEFIT_OPT], $this->game->benefitLabels());
    }

    function testResearchWithEmpiricismReportsBothRollsAndKeepsBothTracks() {
        $this->game->tapestries = [TAP_EMPIRICISM];
        $this->game->seedRand(2, 4);
        $this->game->resolveRow(BE_RESEARCH);

        $this->assertEquals([["science", 2, DieProbeUT::ROLLER], ["science", 4, DieProbeUT::ROLLER]], $this->game->rolls);
        $this->assertEquals(2, $this->game->getGameStateValue("science_die"));
        $this->assertEquals(4, $this->game->getGameStateValue("science_die_empiricism"));
        $this->assertEquals([$this->mark(), $this->mark(), "" . BE_ADVANCE_SCIENCE_BENEFIT_OPT], $this->game->benefitLabels());
    }

    function testAgeOfDiscoveryPutsTheReactingCivAheadOfTheRoller() {
        $this->game->seedRand(3);
        $this->game->effect_ageOfDiscovery(DieProbeUT::ROLLER);

        $this->assertEquals([$this->mark(), "24", "78"], $this->game->benefitLabels());
    }

    function testRow324PutsTheReactingCivAheadOfTheSpendRow() {
        $this->game->seedRand(3);
        $this->assertTrue($this->game->resolveRow(324));

        $this->assertEquals([$this->mark(), "330"], $this->game->benefitLabels());
    }

    function testRow325PutsTheReactingCivAheadOfTheSpendRow() {
        $this->game->seedRand(2);
        $this->assertTrue($this->game->resolveRow(325));

        $this->assertEquals([$this->mark(), "332"], $this->game->benefitLabels());
    }

    /**
     * Moving interruptBenefit() ahead of the roll only reorders what the roll itself queues: rows
     * that were already scheduled when the row started still go last.
     */
    function testRow325KeepsAlreadyScheduledRowsBehindTheRollResult() {
        $this->game->queueBenefitNormal(325, DieProbeUT::ROLLER);
        $this->game->queueBenefitNormal(BE_GAIN_CULTURE, DieProbeUT::ROLLER);
        $row = $this->game->benefitQueue()[0];
        $this->game->seedRand(2);
        $this->game->awardBenefits(DieProbeUT::ROLLER, 325, 1, $row["benefit_data"]);
        $this->game->benefitCashed($row["benefit_id"]);

        $this->assertEquals([$this->mark(), "332", "" . BE_GAIN_CULTURE], $this->game->benefitLabels());
    }

    /** 301 and 304 roll one die twice: the reacting civ hears both rolls and is ahead of the choice. */
    function testRow301PutsBothRollsAheadOfTheChoice() {
        $this->game->seedRand(3, 4);
        $this->assertFalse($this->game->resolveRow(301));

        $this->assertEquals([$this->mark(), $this->mark(), "o,3,4"], $this->game->benefitLabels());
    }

    function testRow303InterleavesEachDieWithItsOwnBenefit() {
        $this->game->seedRand(2, 3);
        $this->assertFalse($this->game->resolveRow(303));

        $this->assertEquals([$this->mark(), "" . BE_VP_TERRITORY, $this->mark(), "" . BE_GAIN_FOOD], $this->game->benefitLabels());
    }

    function testConquerPutsBothDiceAheadOfTheDiePick() {
        $this->game->dbAddStructure(DieProbeUT::ROLLER, BUILDING_OUTPOST, 0, "hand");
        $this->game->seedRand(3, 4);
        $this->game->effect_conquer(DieProbeUT::ROLLER, "1_1", ["1_1"], false, null, "");

        $this->assertEquals([$this->mark(), $this->mark(), "141"], $this->game->benefitLabels());
        $this->assertEquals([["red", 3, DieProbeUT::ROLLER], ["black", 4, DieProbeUT::ROLLER]], $this->game->rolls);
    }
}
