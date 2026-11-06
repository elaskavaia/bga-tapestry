<?php

declare(strict_types=1);

require_once __DIR__ . "/Stubs/GameUT.php";
require_once __DIR__ . "/Stubs/TestWithRealDb.php";

/**
 * ADVISORS approving an opponent's ESPIONAGE gains the card's benefit (112) too. copy_card reads and
 * writes the card table with raw SQL while the lookups go to the in-memory model, so each row goes
 * to both stores.
 */
final class AdvisorsEspionageTest extends TestWithRealDb {
    const ADVISOR = 1;
    const SPY = 2;
    const SPY_TARGET = 1;
    const WHEN_PLAYED_TARGET = 2;
    const WHEN_PLAYED_BENEFIT = 101;
    const ALLIANCE = 5;
    const ALLIANCE_CHOICE = 122;

    private GameUT $game;
    private int $espionage;
    private int $spy_clone;

    private function addCardBoth(string $location, int $player_id, int $type_arg, string $arg2 = "0"): int {
        $id = $this->game->addTapestryRow($location, $player_id, $type_arg, $arg2);
        $this->game->DbQuery(
            "INSERT INTO card (card_id, card_type, card_type_arg, card_location, card_location_arg, card_location_arg2) VALUES ($id, '3', $type_arg, '$location', $player_id, '$arg2')"
        );
        return $id;
    }

    private function copies(int $player_id, int $target_type): void {
        $game = $this->game = new class (2) extends GameUT {
            /** addTapestryClone inserts with raw SQL only, so the new clone is not in the in-memory model */
            function getCardInfoById($card_id, $aliased = false) {
                return parent::getCardInfoById($card_id, $aliased) ?? $this->getObjectFromDB("SELECT * FROM card WHERE card_id='$card_id'");
            }

            function addTapestryRow(string $location, int $player_id, int $type_arg, string $arg2): int {
                return $this->cards->addRow(CARD_TAPESTRY, $type_arg, $location, $player_id, $arg2);
            }
        };
        $game->init();
        $game->doAdjustMaterial(2, 8);
        $game->setGameStateValue("variant_adjustments", 8);
        $game->era = 2;
        $game->giveCiv(self::ADVISOR, CIV_ADVISORS);
        $this->espionage = $this->addCardBoth("era2", self::SPY, TAP_ESPIONAGE);
        $this->spy_clone = $this->addCardBoth("tapestry_{$this->espionage}", self::SPY, self::SPY_TARGET, "clone");
        $target = $this->addCardBoth("era1", self::ADVISOR, $target_type);

        $game->gamestate->changeActivePlayer($player_id);
        $game->gamestate->jumpToState(15);
        $game->queueBenefitNormal(112, $player_id);
        $game->copy_card($target);
    }

    /** The discard of the old clone only reaches the in-memory model, so it is checked there instead */
    private function assertNewCloneOwners(array $expected): void {
        $rows = $this->game->getCollectionFromDB(
            "SELECT card_id, card_location_arg FROM card WHERE card_location='tapestry_{$this->espionage}' AND card_id<>{$this->spy_clone}"
        );
        $this->assertEquals($expected, array_map(fn($row) => (int) $row["card_location_arg"], array_values($rows)));
    }

    private function assertSpyCloneKept(): void {
        $this->assertEquals("tapestry_{$this->espionage}", $this->game->getCardInfoById($this->spy_clone)["card_location"]);
        $this->assertNewCloneOwners([]);
    }

    /** BGA #255108: the advisor's copy no longer replaces the spy's clone. */
    function testAdvisorCopyKeepsTheSpyClone() {
        $this->copies(self::ADVISOR, self::WHEN_PLAYED_TARGET);

        $this->assertSpyCloneKept();
        $this->assertEquals(self::ADVISOR, (int) $this->game->getCurrentBenefit(self::WHEN_PLAYED_BENEFIT)["benefit_player_id"]);
    }

    /** BGA #255108: without an ESPIONAGE of their own a THIS ERA copy gives the advisor nothing. */
    function testAdvisorCopyOfThisEraCardGivesNothing() {
        $this->copies(self::ADVISOR, self::ALLIANCE);

        $this->assertSpyCloneKept();
        $this->assertNull($this->game->getCurrentBenefit(self::ALLIANCE_CHOICE));
    }

    /** The ESPIONAGE owner still replaces their own clone and gets the THIS ERA choice. */
    function testOwnerCopyReplacesOwnClone() {
        $this->copies(self::SPY, self::ALLIANCE);

        $this->assertEquals("limbo", $this->game->getCardInfoById($this->spy_clone)["card_location"]);
        $this->assertNewCloneOwners([self::SPY]);
        $this->assertEquals(self::SPY, (int) $this->game->getCurrentBenefit(self::ALLIANCE_CHOICE)["benefit_player_id"]);
    }
}
