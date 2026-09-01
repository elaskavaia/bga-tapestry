<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertTrue;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Alchemists drive the whole elixir flow through cubes on the mat and dice globals. The cubes live
 * in the GameUT structure table, the dice rolls and the benefit queue are stubbed here so the test
 * can pin what the ability asks for.
 */
class AlchemistsUT extends GameUT {
    public $queued = [];
    public $reentry = 0;
    public $rolls = ["black" => 0, "red" => 0, "science" => 1];

    function rollBlackConquerDie($player_id, bool $undosave) {
        $this->setGameStateValue("conquer_die_black", $this->rolls["black"]);
        return $this->rolls["black"];
    }

    function rollRedConquerDie(int $player_id, bool $undosave) {
        $this->setGameStateValue("conquer_die_red", $this->rolls["red"]);
        return $this->rolls["red"];
    }

    function rollScienceDie($data, $dievar = "science_die", $player_id = -1, $undosave = true) {
        $this->setGameStateValue($dievar, $this->rolls["science"]);
        return $this->rolls["science"];
    }

    function queueBenefitNormal($benefit, $player_id = null, $reason = "", $count = 1) {
        $this->queued[] = $benefit;
    }

    function queueBenefitInterrupt($benefit, $player_id = null, $reason = "", $orderChoice = null) {
        $this->queued[] = $benefit;
    }

    function benefitCivEntry($cid, $player_id, $data = "") {
        $this->reentry++;
    }
}

final class GameTest extends TestCase {
    public $game;

    protected function setUp(): void {
        $this->game = $this->game();
    }

    private function game() {
        $m = new GameUT();
        $m->init();
        return $m;
    }

    public function testGameProgression() {
        $m = $this->game;

        $this->assertNotNull($m);
        $this->assertEquals(0, $m->getGameProgression());
    }

    function testMaterial() {
        $m = $this->game;
        //print("id,name, description\n");
        $m->doAdjustMaterial(2, 8);
        ksort($m->civilizations, SORT_NUMERIC);
        foreach ($m->civilizations as $civ => $civ_data) {
            $description = $civ_data["description"];
            if (is_array($description)) {
                $description = implode("\n", $description);
            }
            $name = $civ_data["name"];
            $al = array_get($civ_data, "al", 4);
            $inst = $m->getCivilizationInstance($civ, false);
            $this->assertEquals($civ, $inst->getType());
            $this->assertNotNull($name);
            //if ($al != 8) print("$name ($civ) \n$description\n\n");
        }
    }

    function testHistorians() {
        $m = $this->game;
        $civ = CIV_HISTORIANS;
        $m->doAdjustMaterial(2, 8);
        $inst = $m->getCivilizationInstance($civ, true);
        $income_trigger = $inst->getRules("income_trigger", null);
        $this->assertNotNull($income_trigger);
        $from = array_get($income_trigger, "from", 0);
        $to = array_get($income_trigger, "to", 0);
        $this->assertEquals($from, 2);
        $this->assertEquals($to, 5);

        $m->doAdjustMaterial(4, 8);
        $income_trigger = $inst->getRules("income_trigger", null);
        $this->assertNotNull($income_trigger);
        $from = array_get($income_trigger, "from", 0);
        $to = array_get($income_trigger, "to", 0);
        $this->assertEquals($from, 1);
        $this->assertEquals($to, 4);
    }

    function testCraftsmen() {
        $m = $this->game;
        $civ = CIV_CRAFTSMEN;
        $m->doAdjustMaterial(2, 8);
        $inst = $m->getCivilizationInstance($civ, true);
        $income_trigger = $inst->getRules("income_trigger", null);
        $this->assertNull($income_trigger);
        $mg = $inst->getRules("midgame_setup", null);
        $this->assertNotNull($mg);
    }

    function testCollectors() {
        $game = $this->game;
        $civ = CIV_COLLECTORS;
        $xciv = $game->getRulesBenefit(BE_COLLECTORS_GRAB, "civ", null);
        $this->assertEquals($civ, $xciv);
        $xciv = $game->getRulesBenefit(BE_COLLECTORS_CARD, "civ", null);
        $this->assertEquals($civ, $xciv);
        $inst = $game->getCivilizationInstance($civ, true);
        $this->assertNotNull($inst);
    }

    function testInfiltrators() {
        $game = $this->game;
        $civ = CIV_INFILTRATORS;
        $xciv = $game->getRulesBenefit(170, "civ", null);
        $this->assertEquals($civ, $xciv);
        $xciv = $game->getRulesBenefit(171, "civ", null);
        $this->assertEquals($civ, $xciv);
        $inst = $game->getCivilizationInstance($civ, true);
        $this->assertNotNull($inst);
    }

    function testTraders() {
        $game = $this->game;
        $civ = CIV_TRADERS;
        // $xciv = $game->getRulesBenefit(170, 'civ', null);
        // $this->assertEquals($civ, $xciv);
        // $xciv = $game->getRulesBenefit(171, 'civ', null);
        // $this->assertEquals($civ, $xciv);
        $game->doAdjustMaterial(2, 8);
        $inst = $game->getCivilizationInstance($civ, true);
        $this->assertNotNull($inst);
        $sben = $inst->getRules("start_benefit", null);
        $this->assertNotNull($sben);
    }

    function testMystics() {
        $game = $this->game;
        $civ = CIV_MYSTICS;
        $game->doAdjustMaterial(2, 8);
        $inst = $game->getCivilizationInstance($civ, true);
        $this->assertNotNull($inst);
        $sben = $inst->getRules("start_benefit", null);
        $this->assertEquals([BE_MYSTIC_TAP_GAIN], $sben);
    }

    function testAdjustmentVariant9Material() {
        $game = $this->game;
        $game->doAdjustMaterial(2, 9);
        $alchemists = $game->civilizations[CIV_ALCHEMISTS];

        $description = implode("\n", $alchemists["description"]);
        $this->assertStringContainsString("Roll the final remaining die", $description);
        $this->assertStringNotContainsString("reroll", $description);

        // no slots@a9 twin, so the a8 benefit pairs are inherited
        $this->assertEquals([BE_TERRITORY_BE_SELECT, BE_ANYRES], $alchemists["slots"][1]["benefit"]);
        $this->assertEquals(5, count($alchemists["slots"]));

        // an a9 twin replaces the a8 value of the same field
        $this->assertStringContainsString("unofficial variant", $alchemists["adjustment"]);

        // compound @a4a8 keys still resolve
        $this->assertEquals([BE_ANYRES, BE_ANYRES, 0, 0], $game->civilizations[CIV_FUTURISTS]["start_benefit"]);

        // a civ with no a9 data falls back to its a8 data
        $this->assertEquals([BE_MYSTIC_TAP_GAIN], $game->civilizations[CIV_MYSTICS]["start_benefit"]);
    }

    function testAdjustmentVariant9Helpers() {
        $game = $this->game;
        $game->setGameStateValue("variant_adjustments", 9);
        $this->assertTrue($game->isAdjustments9());
        $this->assertTrue($game->isAdjustments8());
        $this->assertTrue($game->isAdjustments4or8());
        $this->assertFalse($game->isAdjustments4());

        $game->setGameStateValue("variant_adjustments", 8);
        $this->assertFalse($game->isAdjustments9());
        $this->assertTrue($game->isAdjustments8());

        $this->assertEquals(CIV_ALCHEMISTS, $game->getRulesBenefit(BE_ALCHEMISTS_DIE, "civ", null));
    }

    private function adjustedCivilizations(int $num, int $variant) {
        $game = new GameUT();
        $game->init();
        $game->doAdjustMaterial($num, $variant);
        $applied = [];
        foreach ($game->civilizations as $cid => $civ_info) {
            $applied[$cid] = [];
            foreach ($civ_info as $key => $value) {
                // unresolved "@" twins are inert leftovers, only the applied values matter here
                if (strpos($key, "@") === false) {
                    $applied[$cid][$key] = $value;
                }
            }
        }
        return $applied;
    }

    private function adjustedFakeCiv(int $num, int $variant, array $civ_info) {
        $game = new GameUT();
        $game->init();
        $game->civilizations[900] = $civ_info;
        $game->doAdjustMaterial($num, $variant);
        return $game->civilizations[900];
    }

    function testAdjustmentVariant9MatchesVariant8() {
        foreach ([2, 3, 4, 5] as $num) {
            $a8 = $this->adjustedCivilizations($num, 8);
            $a9 = $this->adjustedCivilizations($num, 9);
            $this->assertTrue(
                $a8[CIV_ALCHEMISTS]["description"] != $a9[CIV_ALCHEMISTS]["description"],
                "alchemists description must be reworked at $num players"
            );
            unset($a8[CIV_ALCHEMISTS]);
            unset($a9[CIV_ALCHEMISTS]);
            $this->assertEquals(json_encode($a8), json_encode($a9), "only the alchemists change at $num players");
        }
    }

    function testAdjustmentVariantOriginalMaterial() {
        foreach ([1, 2] as $variant) {
            $civs = $this->adjustedCivilizations(2, $variant);
            $alchemists = $civs[CIV_ALCHEMISTS];
            $this->assertEquals([97, 98, 99, 100], array_column($alchemists["slots"], "benefit"), "variant $variant slots");
            $this->assertStringContainsString("push their luck", $alchemists["description"][0], "variant $variant description");
            $this->assertStringContainsString("and 10 VP", $alchemists["adjustment"], "variant $variant adjustment");
            $this->assertEquals("", $civs[CIV_ARCHITECTS]["description"][1], "variant $variant has no a4a8 override");
        }
    }

    function testAdjustmentVariant4Material() {
        $civs = $this->adjustedCivilizations(2, 4);
        $alchemists = $civs[CIV_ALCHEMISTS];
        $this->assertEquals([156, 157, 158, 159], array_column($alchemists["slots"], "benefit"), "slots@a4");
        $this->assertEquals("rules change", $alchemists["adjustment"], "adjustment@a4a8");
        $this->assertStringContainsString("RULE CHANGE", $alchemists["description"][1], "description@a4");
        // a partial @a4a8 twin patches one entry and keeps the rest of the base description
        $this->assertStringContainsString("place 1 cube per opponent", $civs[CIV_ARCHITECTS]["description"][1], "description@a4a8");
        $this->assertEquals(5, count($civs[CIV_ARCHITECTS]["description"]), "architects base description kept");
    }

    function testAdjustmentLevelOverrideIsOrderIndependent() {
        $civ = $this->adjustedFakeCiv(2, 9, [
            "name" => "Fake",
            "description" => ["base"],
            "description@a8" => ["a8"],
            "description@a9" => ["a9"],
        ]);
        $this->assertEquals(["a9"], $civ["description"], "a8 declared first");

        $civ = $this->adjustedFakeCiv(2, 9, [
            "name" => "Fake",
            "description" => ["base"],
            "description@a9" => ["a9"],
            "description@a8" => ["a8"],
        ]);
        $this->assertEquals(["a9"], $civ["description"], "a9 declared first");
    }

    function testAdjustmentLevelCompoundOverride() {
        $civ = $this->adjustedFakeCiv(2, 9, [
            "name" => "Fake",
            "description" => ["base"],
            "description@a4a9" => ["a9"],
            "description@a8" => ["a8"],
        ]);
        $this->assertEquals(["a9"], $civ["description"], "compound a9 twin wins over a8");
    }

    function testAdjustmentLevelOverrideRespectsPlayerCount() {
        $table = [
            "name" => "Fake",
            "description" => ["base"],
            "description@a9p2" => ["a9"],
            "description@a8" => ["a8"],
        ];
        $civ = $this->adjustedFakeCiv(2, 9, $table);
        $this->assertEquals(["a9"], $civ["description"], "a9p2 applies at 2 players");

        $civ = $this->adjustedFakeCiv(3, 9, $table);
        $this->assertEquals(["a8"], $civ["description"], "a9p2 must not suppress a8 at 3 players");
    }

    private function alchemistsGame(int $variant) {
        $game = new AlchemistsUT();
        $game->init();
        $game->giveCiv(1, CIV_ALCHEMISTS);
        $game->setGameStateValue("variant_adjustments", $variant);
        $game->doAdjustMaterial(2, $variant);
        return $game;
    }

    function testAlchemists9Elixir() {
        $game = $this->alchemistsGame(9);
        $inst = $game->getCivilizationInstance(CIV_ALCHEMISTS, true);
        $player_id = 1;

        $args = $inst->argCivAbilitySingle($player_id, []);
        $this->assertEquals([0], array_keys($args["slots_choice"]));

        $game->rolls = ["black" => 3, "red" => 0, "science" => 2];
        $inst->moveCivCube($player_id, 0, "", []);
        $this->assertEquals(1, $game->reentry);
        $args = $inst->argCivAbilitySingle($player_id, []);
        $this->assertEquals([1, 2, 3], array_keys($args["slots_choice"]));

        // keep the red die, the other two are re-rolled
        $game->rolls = ["black" => 2, "red" => 5, "science" => 3];
        $inst->moveCivCube($player_id, 2, "", []);
        $this->assertEquals(2, $game->reentry);
        $this->assertEquals(0, $game->getGameStateValue("conquer_die_red"));
        $this->assertEquals(2, $game->getGameStateValue("conquer_die_black"));
        $args = $inst->argCivAbilitySingle($player_id, []);
        $this->assertEquals([1, 3], array_keys($args["slots_choice"]));

        // keep the science die, the last die is rolled and placed without a choice
        $game->rolls = ["black" => 4, "red" => 5, "science" => 1];
        $inst->moveCivCube($player_id, 3, "", []);
        $this->assertEquals(2, $game->reentry);
        $this->assertEquals(3, $game->getGameStateValue("science_die"));
        $this->assertEquals(4, $game->getGameStateValue("conquer_die_black"));

        // red die benefit is gained once, then science or black die is a choice
        $this->assertEquals([[505], ["or" => [24, BE_ALCHEMISTS_DIE]]], $game->queued);
        $this->assertEquals(0, count($inst->getAllCubesOnCiv()));
    }

    function testAlchemists9BlackDieBenefit() {
        $game = $this->alchemistsGame(9);
        $inst = $game->getCivilizationInstance(CIV_ALCHEMISTS, true);

        $game->setGameStateValue("conquer_die_black", 1);
        $inst->awardBenefits(1, BE_ALCHEMISTS_DIE);
        $this->assertEquals([BE_TERRITORY_BE_SELECT, BE_ANYRES], $game->queued[0]);

        $game->setGameStateValue("conquer_die_black", 3);
        $inst->awardBenefits(1, BE_ALCHEMISTS_DIE);
        $this->assertEquals([BE_GAIN_FOOD, BE_EXPLORE], $game->queued[1]);
    }

    function testAlchemists8Elixir() {
        $game = $this->alchemistsGame(8);
        $inst = $game->getCivilizationInstance(CIV_ALCHEMISTS, true);
        $player_id = 1;

        $game->rolls = ["black" => 3, "red" => 0, "science" => 2];
        $inst->moveCivCube($player_id, 0, "", []);
        $args = $inst->argCivAbilitySingle($player_id, []);
        $this->assertEquals([4, 1, 2, 3], array_keys($args["slots_choice"]));

        $game->rolls = ["black" => 2, "red" => 5, "science" => 3];
        $inst->moveCivCube($player_id, 2, "", []);
        $inst->moveCivCube($player_id, 3, "", []);

        // only the two kept dice pay out, and the red one pays twice
        $this->assertEquals([["choice" => [505, 505, 24]]], $game->queued);
    }

    /** LIKE escapes are legal in a search value, a backslash anywhere else is not. */
    function testCheckValueAcceptsLikeEscapes() {
        $game = new GameUT();
        foreach (["tech\\_spot\\_%", "civ\\_49\\_%", "tech_spot_%", "hand"] as $value) {
            $game->checkValue($value, true);
        }
        $this->assertTrue(true);

        foreach (["foo\\'bar", "foo\\bar", "foo\\\\%"] as $value) {
            try {
                $game->checkValue($value, true);
                $this->fail("should reject '$value'");
            } catch (feException $e) {
                $this->assertStringContainsString("alphanum", $e->getMessage());
            }
        }
    }

    /** Outside LIKE an escape has no meaning, so it stays rejected. */
    function testCheckValueRejectsEscapesWithoutLike() {
        $game = new GameUT();
        $this->expectException(feException::class);
        $game->checkValue("tech\\_spot\\_1", false);
    }
}
