<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/PsionicsUT.php";

/**
 * Stage 1 of the PSIONICS plan: the seam that lets a civilization change what a die roll or a deck
 * draw produces, with no civilization in sight. Every case here comes in a pair - what a table
 * where nobody samples sees, which must be exactly what it saw before, and what the seam produces
 * for a player who does.
 */
final class PsionicsSeamTest extends TestCase {
    private PsionicsUT $game;

    protected function setUp(): void {
        $this->game = new PsionicsUT();
        $this->game->init();
    }

    private function sampler(): PsionicsUT {
        $this->game->sample();
        return $this->game;
    }

    // ---------------------------------------------------------- rollDieFaces

    function testEachDieOffersOneFaceAndConsumesOneRandWithoutASampler() {
        $game = $this->game;
        $game->seedRand(3, 4, 2);

        $this->assertEquals([3], $game->rollDieFaces("black", PsionicsUT::ROLLER));
        $this->assertEquals([4], $game->rollDieFaces("red", PsionicsUT::ROLLER));
        $this->assertEquals([2], $game->rollDieFaces("science", PsionicsUT::ROLLER));
        $this->assertEquals([], $game->randQueue);
    }

    function testEachDieOffersTwoFacesAndConsumesTwoRandsForASampler() {
        $game = $this->sampler();
        $game->seedRand(3, 4, 1, 2, 3, 4);

        $this->assertEquals([3, 4], $game->rollDieFaces("black", PsionicsUT::ROLLER));
        $this->assertEquals([1, 2], $game->rollDieFaces("red", PsionicsUT::ROLLER));
        $this->assertEquals([3, 4], $game->rollDieFaces("science", PsionicsUT::ROLLER));
        $this->assertEquals([], $game->randQueue);
    }

    function testTheSixthFaceIsRemappedOnBothFacesOfASampledRoll() {
        $game = $this->sampler();
        $game->seedRand(5, 5, 5, 5);

        $this->assertEquals([1, 1], $game->rollDieFaces("black", PsionicsUT::ROLLER));
        $this->assertEquals([2, 2], $game->rollDieFaces("red", PsionicsUT::ROLLER));
    }

    /** A row that already rolls twice for one choice owns the decision and asks for a plain face. */
    function testACallerThatOwnsTheDecisionGetsOneFace() {
        $game = $this->sampler();
        $game->seedRand(3);

        $this->assertEquals([3], $game->rollDieFaces("black", PsionicsUT::ROLLER, false));
        $this->assertEquals([], $game->randQueue);
    }

    /** A bot never chooses, so a seat that is not a real player gets exactly today's roll. */
    function testASeatThatIsNotARealPlayerNeverSamples() {
        $game = $this->game;
        $game->sample(PsionicsUT::BOT);
        $game->seedRand(3);

        $this->assertEquals([3], $game->rollDieFaces("black", PsionicsUT::BOT));
        $this->assertEquals([], $game->randQueue);
    }

    // ------------------------------------------------- the conquer die globals

    function testAConquerConsumesTwoRandValuesAndStoresNoSecondFace() {
        $game = $this->game;
        $game->seedRand(3, 4);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $this->assertEquals(3, $game->getGameStateValue("conquer_die_red"));
        $this->assertEquals(4, $game->getGameStateValue("conquer_die_black"));
        $this->assertEquals(0, $game->getGameStateValue("conquer_die_red_2"));
        $this->assertEquals(0, $game->getGameStateValue("conquer_die_black_2"));
        $this->assertEquals([3], $game->getConquerDieFaces("red"));
        $this->assertEquals([4], $game->getConquerDieFaces("black"));
    }

    function testASampledConquerConsumesFourRandValuesAndStoresBothFacesPerDie() {
        $game = $this->sampler();
        $game->seedRand(3, 1, 4, 2);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $this->assertEquals([3, 1], $game->getConquerDieFaces("red"));
        $this->assertEquals([4, 2], $game->getConquerDieFaces("black"));
        $this->assertEquals([], $game->randQueue);
    }

    /** Face 0 is a real face, so the global cannot use 0 for "no second face" without an offset. */
    function testASampledFaceOfZeroIsNotMistakenForNoSecondFace() {
        $game = $this->sampler();
        $game->seedRand(3, 0, 4, 0);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $this->assertEquals([3, 0], $game->getConquerDieFaces("red"));
        $this->assertEquals([4, 0], $game->getConquerDieFaces("black"));
    }

    /** Lifetime: the globals belong to the roll that wrote them, never to the one before. */
    function testAPlainRollClearsTheSampledFacesOfTheRollBeforeIt() {
        $game = $this->sampler();
        $game->seedRand(3, 1, 4, 2);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $game->stopSampling();
        $game->seedRand(2, 3);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $this->assertEquals(0, $game->getGameStateValue("conquer_die_red_2"));
        $this->assertEquals(0, $game->getGameStateValue("conquer_die_black_2"));
        $this->assertEquals([2], $game->getConquerDieFaces("red"));
        $this->assertEquals([3], $game->getConquerDieFaces("black"));
    }

    function testASingleDieRollOnlyTouchesItsOwnGlobals() {
        $game = $this->sampler();
        $game->seedRand(3, 1);
        $game->rollBlackConquerDie(PsionicsUT::ROLLER, false);

        $this->assertEquals([3, 1], $game->getConquerDieFaces("black"));
        $this->assertEquals(0, $game->getGameStateValue("conquer_die_red_2"));
    }

    // --------------------------------------------------------- the die pick

    function testKeepingAFaceCollapsesTheDieToIt() {
        $game = $this->sampler();
        $game->seedRand(3, 1, 4, 2);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $game->keepConquerDieFace("red", 1);

        $this->assertEquals([1], $game->getConquerDieFaces("red"));
        $this->assertEquals(0, $game->getGameStateValue("conquer_die_red_2"));
    }

    function testKeepingAFaceThatWasNotRolledIsRefused() {
        $game = $this->sampler();
        $game->seedRand(3, 1, 4, 2);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $this->expectException(BgaUserException::class);
        $game->keepConquerDieFace("red", 4);
    }

    /** A die that offered one face has nothing to keep, which is every roll without a sampler. */
    function testKeepingAFaceIsANoOpOnAPlainRoll() {
        $game = $this->game;
        $game->seedRand(3, 4);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $game->keepConquerDieFace("red", -1);

        $this->assertEquals([3], $game->getConquerDieFaces("red"));
    }

    /** FORMAL_RULES CIV.PSIONICS.9: the roller rerolled it, so the sampled face is what shows. */
    function testTheUnclaimedDiePaysTradersOnItsSecondFace() {
        $game = $this->sampler();
        $game->seedRand(3, 1, 4, 2);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $game->queueUnclaimedDieBenefit("red", PsionicsUT::OTHER);

        // red face 1 is the second face rolled, red face 3 was the first
        $this->assertEquals([[PsionicsUT::OTHER, 506]], $game->rows());
    }

    function testTheUnclaimedDiePaysTradersItsOnlyFaceOnAPlainRoll() {
        $game = $this->game;
        $game->seedRand(3, 4);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $game->queueUnclaimedDieBenefit("red", PsionicsUT::OTHER);

        $this->assertEquals([[PsionicsUT::OTHER, 504]], $game->rows());
    }

    /** The both dice path has no pick state, so each die queues its own choose one. */
    function testADieKeptOutrightOffersAChoiceOverItsFaces() {
        $game = $this->sampler();
        $game->seedRand(3, 1, 4, 2);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $game->queueConquerDieGain("red", PsionicsUT::ROLLER);

        $this->assertEquals(["o,504,506"], $game->benefitLabels());
    }

    function testADieKeptOutrightQueuesOneRowOnAPlainRoll() {
        $game = $this->game;
        $game->seedRand(3, 4);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $game->queueConquerDieGain("red", PsionicsUT::ROLLER);

        $this->assertEquals(["504"], $game->benefitLabels());
    }

    /** Black face 1 is the territory benefit, which is empty when the hex has none. */
    function testAFaceWithNoBenefitDropsOutOfTheChoiceWithoutSuppressingTheOther() {
        $game = $this->sampler();
        $game->seedRand(1, 3);
        $game->rollBlackConquerDie(PsionicsUT::ROLLER, false);

        $game->queueConquerDieGain("black", PsionicsUT::ROLLER);

        $this->assertEquals(["" . BE_GAIN_FOOD], $game->benefitLabels());
    }

    function testBothFacesWithoutABenefitQueueNothing() {
        $game = $this->sampler();
        $game->seedRand(1, 1);
        $game->rollBlackConquerDie(PsionicsUT::ROLLER, false);

        $game->queueConquerDieGain("black", PsionicsUT::ROLLER);

        $this->assertEquals([], $game->benefitLabels());
    }

    /** The territory face goes into the choice as its own row, so the whole tile is paid, not its first entry. */
    function testBlackFaceOneOffersTheTerritoryBenefit() {
        $game = $this->sampler();
        $game->tileBenefit = [BE_GAIN_COIN, BE_VP_TERRITORY];
        $game->seedRand(1, 3);
        $game->rollBlackConquerDie(PsionicsUT::ROLLER, false);

        $game->queueConquerDieGain("black", PsionicsUT::ROLLER);

        $this->assertEquals(["o," . BE_TERRITORY_BE_BLACKDIE . "," . BE_GAIN_FOOD], $game->benefitLabels());
    }

    function testTheTerritoryFaceRowPaysTheWholeTile() {
        $game = $this->sampler();
        $game->tileBenefit = [BE_GAIN_COIN, BE_VP_TERRITORY];
        $this->assertTrue($game->resolveRow(BE_TERRITORY_BE_BLACKDIE));

        $this->assertEqualsCanonicalizing(["" . BE_GAIN_COIN, "" . BE_VP_TERRITORY], $game->benefitLabels());
    }

    /** Two equal faces are one option, not two identical buttons. */
    function testTwoEqualFacesCollapseToOneGain() {
        $game = $this->sampler();
        $game->seedRand(3, 3, 4, 2);
        $game->rollConquerDice(PsionicsUT::ROLLER);

        $game->queueConquerDieGain("red", PsionicsUT::ROLLER);

        $this->assertEquals(["504"], $game->benefitLabels());
    }

    function testRow301OffersTheTerritoryFaceAsItsOwnRow() {
        $game = $this->sampler();
        $game->tileBenefit = [BE_GAIN_COIN, BE_VP_TERRITORY];
        $game->seedRand(1, 2, 3);
        $this->assertFalse($game->resolveRow(301));

        $this->assertEquals(["o," . BE_TERRITORY_BE_BLACKDIE . "," . BE_GAIN_WORKER . "," . BE_GAIN_FOOD], $game->benefitLabels());
    }

    // ------------------------------------------------------------- research

    function testResearchOffersOnlyTheRolledTrackWithoutASampler() {
        $game = $this->game;
        $game->seedRand(3);
        $game->queueBenefitNormal(BE_RESEARCH, PsionicsUT::ROLLER);
        $game->runManager();

        $args = $game->argResearch();
        $this->assertEquals(3, $args["science"]);
        $this->assertEquals(0, $args["empiricism"]);
        $this->assertEquals(0, $args["psionics"]);
    }

    function testResearchOffersTwoTracksToASamplerWithoutEmpiricism() {
        $game = $this->sampler();
        $game->seedRand(3, 1);
        $game->queueBenefitNormal(BE_RESEARCH, PsionicsUT::ROLLER);
        $game->runManager();

        $args = $game->argResearch();
        $this->assertEquals(3, $args["science"]);
        $this->assertEquals(0, $args["empiricism"]);
        $this->assertEquals(1, $args["psionics"]);
    }

    /** One extra roll per decision, not per roll: three options, never four. */
    function testResearchOffersThreeTracksWithEmpiricismAndNeverFour() {
        $game = $this->sampler();
        $game->tapestries = [TAP_EMPIRICISM];
        $game->seedRand(3, 2, 1);
        $game->queueBenefitNormal(BE_RESEARCH, PsionicsUT::ROLLER);
        $game->runManager();

        $args = $game->argResearch();
        $this->assertEquals([3, 2, 1], [$args["science"], $args["empiricism"], $args["psionics"]]);
        $this->assertEquals([], $game->randQueue);
    }

    /**
     * The advance itself reads the cubes on the technology track with raw SQL, which the in memory
     * model does not answer, so the case stops there. What it pins is that the sampled track got
     * past the selection check and that the decision settled all three globals.
     */
    function testTheResearchDecisionAcceptsTheSampledTrackAndClearsAllThree() {
        $game = $this->sampler();
        $game->tapestries = [TAP_EMPIRICISM];
        $game->seedRand(3, 2, 1);
        $game->queueBenefitNormal(BE_RESEARCH, PsionicsUT::ROLLER);
        $game->runManager();

        try {
            $game->action_research_decision(1, 1);
        } catch (BgaUserException $e) {
            $this->assertStringNotContainsString("You can only select", $e->getMessage());
        }

        $this->assertEquals(1, $game->getGameStateValue("science_die"));
        $this->assertEquals(0, $game->getGameStateValue("science_die_empiricism"));
        $this->assertEquals(0, $game->getGameStateValue("science_die_psionics"));
    }

    function testTheResearchDecisionRefusesATrackThatWasNotRolled() {
        $game = $this->sampler();
        $game->seedRand(3, 1);
        $game->queueBenefitNormal(BE_RESEARCH, PsionicsUT::ROLLER);
        $game->runManager();

        $this->expectException(BgaUserException::class);
        $game->action_research_decision(4, 1);
    }

    /** Lifetime again: the third global belongs to the decision that rolled it. */
    function testThePlainResearchRollClearsTheSampledTrackBeforeIt() {
        $game = $this->sampler();
        $game->seedRand(3, 1);
        $game->queueBenefitNormal(BE_RESEARCH, PsionicsUT::ROLLER);
        $game->runManager();
        $this->assertEquals(1, $game->getGameStateValue("science_die_psionics"));

        $game->stopSampling();
        $game->seedRand(2);
        $game->rollScienceDie2("");

        $this->assertEquals(0, $game->getGameStateValue("science_die_psionics"));
    }

    // ------------------------------------------------------- the rolling rows

    function testRow302RollsTwiceAndOffersTwoWithoutASampler() {
        $game = $this->game;
        $game->seedRand(3, 1);
        $this->assertFalse($game->resolveRow(302));

        $this->assertEquals(["o,24,22"], $game->benefitLabels());
    }

    function testRow302RollsThreeTimesAndOffersThreeForASampler() {
        $game = $this->sampler();
        $game->seedRand(3, 1, 2);
        $this->assertFalse($game->resolveRow(302));

        $this->assertEquals(["o,24,22,23"], $game->benefitLabels());
        $this->assertEquals([], $game->randQueue);
    }

    function testRow301RollsThreeTimesAndOffersThreeForASampler() {
        $game = $this->sampler();
        $game->seedRand(0, 2, 3);
        $this->assertFalse($game->resolveRow(301));

        $this->assertEquals(["o," . BE_GAIN_COIN . "," . BE_GAIN_WORKER . "," . BE_GAIN_FOOD], $game->benefitLabels());
        $this->assertEquals([], $game->randQueue);
    }

    function testRow301RollsTwiceWithoutASampler() {
        $game = $this->game;
        $game->seedRand(0, 2);
        $this->assertFalse($game->resolveRow(301));

        $this->assertEquals(["o," . BE_GAIN_COIN . "," . BE_GAIN_WORKER], $game->benefitLabels());
    }

    /** Two independent gains, so each roll of 303 gets its own sampled face and its own choice. */
    function testRow303GivesEachRollItsOwnChoiceForASampler() {
        $game = $this->sampler();
        $game->seedRand(2, 3, 0, 2);
        $this->assertFalse($game->resolveRow(303));

        $this->assertEquals(["o," . BE_VP_TERRITORY . ",504", "o," . BE_GAIN_COIN . "," . BE_GAIN_WORKER], $game->benefitLabels());
        $this->assertEquals([], $game->randQueue);
    }

    function testRow303GainsBothDiceOutrightWithoutASampler() {
        $game = $this->game;
        $game->seedRand(2, 3);
        $this->assertFalse($game->resolveRow(303));

        $this->assertEquals(["" . BE_VP_TERRITORY, "" . BE_GAIN_FOOD], $game->benefitLabels());
    }

    function testRow304GivesEachRollItsOwnChoiceForASampler() {
        $game = $this->sampler();
        $game->seedRand(2, 3, 0, 4);
        $this->assertFalse($game->resolveRow(304));

        $this->assertEquals(["o," . BE_VP_TERRITORY . ",504", "o,505,507"], $game->benefitLabels());
    }

    function testRow325OffersBothFacesInsteadOfRow332() {
        $game = $this->sampler();
        $game->seedRand(3, 1);
        $this->assertTrue($game->resolveRow(325));

        $this->assertEquals(["o,24,22"], $game->benefitLabels());
    }

    function testRow325QueuesRow332WithoutASampler() {
        $game = $this->game;
        $game->seedRand(3);
        $this->assertTrue($game->resolveRow(325));

        $this->assertEquals(["332"], $game->benefitLabels());
    }

    /** Every roll of the reroll loop samples again: a reroll replaces a result, it does not add one. */
    function testRow325KeepsTheRerollOptionAlongsideBothFaces() {
        $game = $this->sampler();
        $game->seedRand(3, 1);
        $this->assertTrue($game->resolveRow(325, 2));

        $this->assertEquals(["o,24,22,202", "603", "325"], $game->benefitLabels());
    }

    function testRow325KeepsTodaysRerollOptionWithoutASampler() {
        $game = $this->game;
        $game->seedRand(3);
        $this->assertTrue($game->resolveRow(325, 2));

        $this->assertEquals(["o,332,202", "603", "325"], $game->benefitLabels());
    }

    function testRow324OffersBothFacesInsteadOfRow330() {
        $game = $this->sampler();
        $game->seedRand(0, 3);
        $this->assertTrue($game->resolveRow(324));

        $this->assertEquals(["o," . BE_GAIN_COIN . "," . BE_GAIN_FOOD], $game->benefitLabels());
    }

    function testRow324QueuesRow330WithoutASampler() {
        $game = $this->game;
        $game->seedRand(0);
        $this->assertTrue($game->resolveRow(324));

        $this->assertEquals(["330"], $game->benefitLabels());
    }

    function testRow324KeepsTodaysRerollOptionWithoutASampler() {
        $game = $this->game;
        $game->seedRand(0);
        $this->assertTrue($game->resolveRow(324, 2));

        $this->assertEquals(["o,330,202", "603", "324"], $game->benefitLabels());
    }

    // ---------------------------------------------------------- the card seam

    function testACardGainGoesStraightToHandWithoutASampler() {
        $game = $this->game;
        $game->fillDeck(CARD_TERRITORY, 4);
        $this->assertTrue($game->resolveRow(BE_TERRITORY));

        $this->assertCount(1, $game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY));
        $this->assertEquals([], $game->benefitLabels());
    }

    function testACardGainBecomesADrawOfTwoAndAKeepForASampler() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TERRITORY, 4);
        $this->assertTrue($game->resolveRow(BE_TERRITORY));

        $this->assertEquals([[PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY]], $game->rows());

        $game->runManager();
        $this->assertEquals("keepCard", $game->stateName());
        $this->assertCount(2, $game->getCardsSearch(CARD_TERRITORY, null, "draw", PsionicsUT::ROLLER));
    }

    /** One sample per card gained, so a gain of two is two separate draws and two separate keeps. */
    function testAGainOfTwoCardsIsTwoSeparateChoices() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TERRITORY, 6);
        $this->assertTrue($game->resolveRow(BE_TERRITORY, 2));

        $this->assertEquals([[PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY], [PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY]], $game->rows());
    }

    /** Additive, not multiplicative: draw 3 keep 1 becomes draw 4 keep 1. */
    function testADrawAndKeepRowDrawsOneMoreForASampler() {
        $game = $this->sampler();
        $game->fillDeck(CARD_CIVILIZATION, 6);
        $this->assertFalse($game->resolveRow(172));

        $this->assertCount(4, $game->getCardsSearch(CARD_CIVILIZATION, null, "draw", PsionicsUT::ROLLER));
    }

    function testADrawAndKeepRowDrawsItsPrintedCountWithoutASampler() {
        $game = $this->game;
        $game->fillDeck(CARD_CIVILIZATION, 6);
        $this->assertFalse($game->resolveRow(172));

        $this->assertCount(3, $game->getCardsSearch(CARD_CIVILIZATION, null, "draw", PsionicsUT::ROLLER));
    }

    /** The sampled rows are the sample; resolving one must not enlarge it a second time. */
    function testASampledRowIsNotEnlargedAgain() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TAPESTRY, 6);
        $this->assertFalse($game->resolveRow(BE_PSIONICS_TAPESTRY));

        $this->assertCount(2, $game->getCardsSearch(CARD_TAPESTRY, null, "draw", PsionicsUT::ROLLER));
    }

    function testAKeptCivilizationStaysInTheDrawArea() {
        $game = $this->sampler();
        $game->fillDeck(CARD_CIVILIZATION, 4);
        $this->assertFalse($game->resolveRow(BE_PSIONICS_CIV));
        $drawn = $game->drawn(CARD_CIVILIZATION);

        $game->effect_keepCard([$drawn[0]], PsionicsUT::ROLLER, $game->getCurrentBenefitWithInfo());

        $this->assertEquals([$drawn[0]], $game->drawn(CARD_CIVILIZATION));
        $this->assertNotContains($drawn[0], array_keys($game->getCardsInHand(PsionicsUT::ROLLER, CARD_CIVILIZATION)));
    }

    function testAKeptTerritoryMovesToHandAndTheRestAreDiscarded() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TERRITORY, 4);
        $this->assertFalse($game->resolveRow(BE_PSIONICS_TERRITORY));
        $drawn = $game->drawn(CARD_TERRITORY);

        $game->effect_keepCard([$drawn[0]], PsionicsUT::ROLLER, $game->getCurrentBenefitWithInfo());

        $this->assertEquals([$drawn[0]], array_keys($game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY)));
        $this->assertCount(1, $game->getCardsSearch(CARD_TERRITORY, null, "discard"));
    }

    /** A deck with one card left offers one candidate rather than asserting. */
    function testAnAlmostEmptyDeckOffersWhatIsLeft() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TERRITORY, 1);
        $this->assertFalse($game->resolveRow(BE_PSIONICS_TERRITORY));

        $this->assertCount(1, $game->getCardsSearch(CARD_TERRITORY, null, "draw", PsionicsUT::ROLLER));
    }

    function testTheInfiltratorsArmStillFiresAheadOfTheSeam() {
        $game = $this->game;
        $game->giveCiv(PsionicsUT::ROLLER, CIV_INFILTRATORS);
        $this->assertTrue($game->resolveRow(BE_GAIN_CIV));

        $this->assertEquals([[PsionicsUT::ROLLER, 172]], $game->rows());
    }

    /** Row 174 is a civilization row too and must move the card to hand, or it re-queues itself forever. */
    function testAKeptCivilizationComesIntoPlayThroughRow174() {
        $game = $this->sampler();
        $game->fillDeck(CARD_CIVILIZATION, 4, CIV_PSIONICS);
        $this->assertFalse($game->resolveRow(BE_PSIONICS_CIV));
        $sample = $game->benefitQueue()[0];
        $drawn = $game->drawn(CARD_CIVILIZATION);

        $game->effect_keepCard([$drawn[0]], PsionicsUT::ROLLER, $game->getCurrentBenefitWithInfo());
        $game->benefitCashed($sample["benefit_id"]);
        $this->assertEquals([[PsionicsUT::ROLLER, 174]], $game->rows());
        $game->runManager();

        $this->assertEquals([], $game->drawn(CARD_CIVILIZATION));
        $this->assertContains($drawn[0], array_keys($game->getCardsInHand(PsionicsUT::ROLLER, CARD_CIVILIZATION)));
    }

    function testAKeptTechnologyWithTheUpgradeFlagIsUpgradedAsItComesIntoPlay() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TECHNOLOGY, 4);
        $this->assertFalse($game->resolveRow(BE_PSIONICS_TECH_UPGRADE));
        $drawn = $game->drawn(CARD_TECHNOLOGY);

        $game->effect_keepCard([$drawn[0]], PsionicsUT::ROLLER, $game->getCurrentBenefitWithInfo());

        $this->assertEquals([$drawn[0]], array_keys($game->getCardsInHand(PsionicsUT::ROLLER, CARD_TECHNOLOGY)));
        $this->assertEquals([$drawn[0]], $game->upgraded);
    }

    function testAKeptTechnologyWithoutTheUpgradeFlagIsNotUpgraded() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TECHNOLOGY, 4);
        $this->assertFalse($game->resolveRow(BE_PSIONICS_TECH));
        $drawn = $game->drawn(CARD_TECHNOLOGY);

        $game->effect_keepCard([$drawn[0]], PsionicsUT::ROLLER, $game->getCurrentBenefitWithInfo());

        $this->assertEquals([$drawn[0]], array_keys($game->getCardsInHand(PsionicsUT::ROLLER, CARD_TECHNOLOGY)));
        $this->assertEquals([], $game->upgraded);
    }

    // --------------------------------------------------------- the invent seam

    function testInventingFromTheTopOfTheDeckDrawsOneCardWithoutASampler() {
        $game = $this->game;
        $game->fillDeck(CARD_TECHNOLOGY, 4);
        $game->queueBenefitNormal(BE_INVENT, PsionicsUT::ROLLER);
        $game->runManager();
        $this->assertEquals("invent", $game->stateName());

        $game->action_invent(0);

        $this->assertCount(1, $game->getCardsInHand(PsionicsUT::ROLLER, CARD_TECHNOLOGY));
        $this->assertEquals([], $game->rows());
    }

    function testInventingFromTheTopOfTheDeckBecomesASampleForASampler() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TECHNOLOGY, 4);
        $game->queueBenefitNormal(BE_INVENT, PsionicsUT::ROLLER);
        $game->runManager();

        $game->action_invent(0);

        $this->assertEquals([[PsionicsUT::ROLLER, BE_PSIONICS_TECH]], $game->rows());
        $this->assertCount(0, $game->getCardsInHand(PsionicsUT::ROLLER, CARD_TECHNOLOGY));
    }

    /** Row 127 invents and upgrades in one go, so its sample has to carry the upgrade to the keep. */
    function testInventAndUpgradeFromTheDeckBecomesTheUpgradingSample() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TECHNOLOGY, 4);
        $game->queueBenefitNormal(127, PsionicsUT::ROLLER);
        $game->runManager();

        $game->action_invent(0);

        $this->assertEquals([[PsionicsUT::ROLLER, BE_PSIONICS_TECH_UPGRADE]], $game->rows());
    }

    // ------------------------------------------------------ an opponent's gain

    /** FORMAL_RULES CIV.PSIONICS.8: a gain handed to an opponent is that opponent's gain, sampled by their own civ. */
    function testAnOpponentWhoSamplesKeepsTheirOwnGain() {
        $game = $this->game;
        $game->sample(PsionicsUT::OTHER);
        $game->fillDeck(CARD_TECHNOLOGY, 4);
        $this->assertTrue($game->resolveRow(199));

        $this->assertEquals([[PsionicsUT::OTHER, BE_PSIONICS_TECH]], $game->rows());
        $this->assertCount(0, $game->getCardsInHand(PsionicsUT::OTHER, CARD_TECHNOLOGY));
    }

    function testTheRollersCivDoesNotSampleAnOpponentsGain() {
        $game = $this->sampler();
        $game->fillDeck(CARD_TECHNOLOGY, 4);
        $this->assertTrue($game->resolveRow(199));

        $this->assertEquals([], $game->rows());
        $this->assertCount(1, $game->getCardsInHand(PsionicsUT::OTHER, CARD_TECHNOLOGY));
    }
}
