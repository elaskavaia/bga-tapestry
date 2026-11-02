<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/IncomeMatUT.php";

/**
 * Traders under the Adjustment Civilization Pack (variant 8): both the trader token option and the
 * income building option trade a territory tile away to the opponent whose outpost shares the
 * territory. The income mat rules of the building option live in IncomeMatTest.
 *
 * Player ids start at 11 so they stay clear of PLAYER_AUTOMA (1) and PLAYER_SHADOW (2).
 */
final class TradersTest extends TestCase {
    const PLACED_TILE = 1; // BE_GAIN_COIN
    const GIVEN_TILE = 3; // BE_GAIN_FOOD

    private IncomeMatUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame(8);
    }

    private function newGame(int $variant): IncomeMatUT {
        $game = new IncomeMatUT(2);
        $game->init();
        $game->doAdjustMaterial(2, $variant);
        $game->setGameStateValue("variant_adjustments", $variant);
        $game->giveCiv(IncomeMatUT::OWNER, CIV_TRADERS);
        $game->setTile("2_0", self::PLACED_TILE);
        $game->addOutpostAt(IncomeMatUT::OPPONENT, "2_0");
        return $game;
    }

    private function giveTile(int $player_id = IncomeMatUT::OWNER): int {
        return $this->game->addCard(CARD_TERRITORY, "hand", $player_id, self::GIVEN_TILE);
    }

    /** Pending rows as [benefit, player] pairs, in pop order. */
    private function queued(): array {
        return array_map(fn($row) => [(int) $row["benefit_type"], (int) $row["benefit_player_id"]], $this->game->benefitQueue());
    }

    private function tilesOf(int $player_id): array {
        return array_keys($this->game->getCardsInHand($player_id, CARD_TERRITORY));
    }

    // -------------------------------------------- trader token (BGA #188139)

    /** The traded tile leaves the Traders hand for the opponent's. */
    function testTheTokenOptionGivesTheChosenTileToTheOpponent() {
        $tile = $this->giveTile();

        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "tile" => $tile]);

        $this->assertEquals([], $this->tilesOf(IncomeMatUT::OWNER));
        $this->assertEquals([$tile], $this->tilesOf(IncomeMatUT::OPPONENT));
    }

    /** Both the territory benefit and the traded tile benefit are the Traders player's own. */
    function testTheTokenOptionPaysTheTraderBothBenefits() {
        $tile = $this->giveTile();

        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "tile" => $tile]);

        $this->assertEquals([[BE_GAIN_COIN, IncomeMatUT::OWNER], [BE_GAIN_FOOD, IncomeMatUT::OWNER]], $this->queued());
    }

    // ------------------------------------------ income building (BGA #188139)

    /** The building option trades a tile away too, which the pack rules added over variant 4. */
    function testTheIncomeBuildingOptionGivesTheChosenTileToTheOpponent() {
        $tile = $this->giveTile();
        $this->game->layoutPrefix(IncomeMatUT::OWNER, BUILDING_ARMORY, 1);

        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "bt" => BUILDING_ARMORY, "tile" => $tile]);

        $this->assertEquals([], $this->tilesOf(IncomeMatUT::OWNER));
        $this->assertEquals([$tile], $this->tilesOf(IncomeMatUT::OPPONENT));
    }

    /** "both you and your opponent gain the benefit revealed by the income building". */
    function testTheIncomeBuildingOptionPaysTheRevealedBenefitToBothPlayers() {
        $tile = $this->giveTile();
        $this->game->layoutPrefix(IncomeMatUT::OWNER, BUILDING_ARMORY, 1);
        $revealed = $this->game->income_tracks[BUILDING_ARMORY][2]["benefit"];
        $this->assertCount(1, $revealed, "the fixture assumes a single benefit on the revealed spot");

        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "bt" => BUILDING_ARMORY, "tile" => $tile]);

        $this->assertEquals([[$revealed[0], IncomeMatUT::OWNER], [$revealed[0], IncomeMatUT::OPPONENT]], $this->queued());
    }

    // ------------------------------------------------------- what can be given

    function testATileThatIsNotYoursCannotBeGivenAway() {
        $tile = $this->giveTile(IncomeMatUT::OPPONENT);

        $this->expectException(BgaUserException::class);
        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "tile" => $tile]);
    }

    /** The tile already on the map at the target is a card too, and is not in anyone's hand. */
    function testATileOnTheMapCannotBeGivenAway() {
        $onmap = array_key_first($this->game->getCardsSearch(CARD_TERRITORY, null, "map", null, "2_0"));

        $this->expectException(BgaUserException::class);
        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "tile" => $onmap]);
    }

    function testACardThatIsNotATerritoryTileCannotBeGivenAway() {
        $civ = $this->game->giveCiv(IncomeMatUT::OWNER, CIV_TRADERS);

        $this->expectException(BgaUserException::class);
        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "tile" => $civ]);
    }

    function testAnUnknownCardIdCannotBeGivenAway() {
        $this->expectException(BgaUserException::class);
        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "tile" => 9999]);
    }

    /** An empty hand is a legal-but-wrong click, so it is a player error and nothing has moved yet. */
    function testTheTokenOptionCannotBeTakenWithoutATileToGive() {
        $this->expectException(BgaUserException::class);
        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0"]);
    }

    function testTheBuildingOptionCannotBeTakenWithoutATileToGive() {
        $this->game->layoutPrefix(IncomeMatUT::OWNER, BUILDING_ARMORY, 1);

        try {
            $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "bt" => BUILDING_ARMORY]);
            $this->fail("expected the missing tile to be refused");
        } catch (BgaUserException $expected) {
        }
        $this->assertEquals(1, $this->game->dbGetIncomeTrackLevel(BUILDING_ARMORY, IncomeMatUT::OWNER), "the mat was not touched");
        $this->assertEquals([], $this->game->benefitQueue());
    }

    /** The Shadow Empire holds no cards, so its traded tile leaves the game rather than vanishing into a bot hand. */
    function testATileTradedToTheShadowEmpireIsDiscarded() {
        $game = new IncomeMatUT(2);
        $game->init();
        $game->doAdjustMaterial(2, 8);
        $game->setGameStateValue("variant_adjustments", 8);
        $game->giveCiv(IncomeMatUT::OWNER, CIV_TRADERS);
        $game->setTile("2_0", self::PLACED_TILE);
        $game->addOutpostAt(PLAYER_SHADOW, "2_0");
        $this->game = $game;
        $tile = $this->giveTile();

        $game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "tile" => $tile]);

        $this->assertEquals([], $this->tilesOf(IncomeMatUT::OWNER));
        $this->assertEquals([], $this->tilesOf(PLAYER_SHADOW));
        $this->assertArrayHasKey($tile, $game->getCardsSearch(CARD_TERRITORY, null, "discard"));
    }

    /** Variant 4 keeps its own rule: the opponent alone is paid, and no tile changes hands. */
    function testVariantFourPaysOnlyTheOpponentAndTradesNothing() {
        $this->game = $this->newGame(4);
        $tile = $this->giveTile();
        $this->game->layoutPrefix(IncomeMatUT::OWNER, BUILDING_ARMORY, 1);
        $revealed = $this->game->income_tracks[BUILDING_ARMORY][2]["benefit"];

        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "bt" => BUILDING_ARMORY]);

        $this->assertEquals([[$revealed[0], IncomeMatUT::OPPONENT]], $this->queued());
        $this->assertEquals([$tile], $this->tilesOf(IncomeMatUT::OWNER));
    }

    /** Default rules: the trader token pays the territory benefit only, and trades nothing. */
    function testDefaultRulesPayTheTerritoryBenefitAndTradeNothing() {
        $this->game = $this->newGame(2);
        $tile = $this->giveTile();

        $this->game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0"]);

        $this->assertEquals([[BE_GAIN_COIN, IncomeMatUT::OWNER]], $this->queued());
        $this->assertEquals([$tile], $this->tilesOf(IncomeMatUT::OWNER));
    }
}
