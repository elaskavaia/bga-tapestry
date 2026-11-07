import { makeBga } from "./setup.js";
import { expect } from "chai";
import { Game } from "../../modules/js/Game.js";

const ME = 10;
const OTHER = 20;
const [TERRITORY, SPACE, TAPESTRY, TECHNOLOGY, CIVILIZATION] = [1, 2, 3, 4, 5];

function makeGame() {
  const gamedatas = {
    players: {
      [ME]: { id: ME, name: "Alice", color: "ff0000" },
      [OTHER]: { id: OTHER, name: "Bob", color: "0000ff" }
    }
  };
  const game = new Game(makeBga(gamedatas, ME));
  game.CON = {
    CARD_TERRITORY: TERRITORY,
    CARD_SPACE: SPACE,
    CARD_TAPESTRY: TAPESTRY,
    CARD_TECHNOLOGY: TECHNOLOGY,
    CARD_CIVILIZATION: CIVILIZATION
  };
  return game;
}

describe("Game.bgaFormatText", () => {
  it("shows log_others to a player other than the actor", () => {
    const { log } = makeGame().bgaFormatText("${You} keep a card", { player_id: OTHER, log_others: "${player_name} keeps a card" });
    expect(log).to.equal("${player_name} keeps a card");
  });

  it("keeps the own log for the actor", () => {
    const { log } = makeGame().bgaFormatText("${You} keep a card", { player_id: ME, log_others: "${player_name} keeps a card" });
    expect(log).to.equal("${You} keep a card");
  });

  it("replaces ${You} with the colored current player name, once", () => {
    const args = { player_id: ME };
    const game = makeGame();
    game.bgaFormatText("${You} advance", args);
    expect(args.You).to.contain("ff0000").and.contain("You");

    args.You = "unchanged";
    game.bgaFormatText("${You} advance", args);
    expect(args.You).to.equal("unchanged");
  });
});

describe("Game.getCardDivLocatonId", () => {
  const game = makeGame();
  const locate = (card_type, card_location, card_location_arg = ME, card_location_arg2 = 0) =>
    game.getCardDivLocatonId({ card_id: 1, card_type_arg: 7, card_type, card_location, card_location_arg, card_location_arg2 });

  it("maps territory tiles", () => {
    expect(locate(TERRITORY, "hand")).to.equal("territory_tiles_" + ME);
    expect(locate(TERRITORY, "discard")).to.equal("territory_deck");
    expect(locate(TERRITORY, "deck_territory")).to.equal("territory_deck");
    expect(locate(TERRITORY, "draw")).to.equal("draw");
    expect(locate(TERRITORY, "map", ME, "4_5")).to.equal("land_4_5");
    expect(locate(TERRITORY, "civ_21_3")).to.equal("civ_21_3");
  });

  it("maps space tiles", () => {
    expect(locate(SPACE, "hand")).to.equal("space_tiles_" + ME);
    expect(locate(SPACE, "hand_space")).to.equal("space_explored_" + ME);
    expect(locate(SPACE, "deck_space")).to.equal("space_deck");
    expect(locate(SPACE, "discard")).to.equal("space_deck");
  });

  it("maps tapestry cards", () => {
    expect(locate(TAPESTRY, "hand")).to.equal("tapestry_cards_" + ME);
    expect(locate(TAPESTRY, "era3")).to.equal("tapestry_slot_" + ME + "_3");
    expect(locate(TAPESTRY, "tapestry_5")).to.equal("card_5");
    expect(locate(TAPESTRY, "discard_13")).to.equal("deck_13");
    expect(locate(TAPESTRY, "submerged")).to.equal("submerged_cards_" + ME);
    expect(locate(TAPESTRY, "discard")).to.equal("tapestry_deck");
    expect(locate(TAPESTRY, "deck_tapestry")).to.equal("tapestry_deck");
  });

  it("maps a tech card in hand to its holder slot", () => {
    expect(locate(TECHNOLOGY, "hand", ME, 2)).to.equal("tech_holder_" + ME + "_2");
    expect(locate(TECHNOLOGY, "hand", ME, 3)).to.equal("tech_holder_" + ME + "_0");
    expect(locate(TECHNOLOGY, "deck_tech_vis")).to.equal("tech_deck_visible");
    expect(locate(TECHNOLOGY, "discard")).to.equal("tech_discard");
    expect(locate(TECHNOLOGY, "deck_tech")).to.equal("deck_tech");
  });

  it("maps civilization cards", () => {
    expect(locate(CIVILIZATION, "hand")).to.equal("civilization_holder_" + ME);
    expect(locate(CIVILIZATION, "choice")).to.equal("civilizations");
    expect(locate(CIVILIZATION, "discard")).to.equal("civilization_deck");
  });

  it("accepts the short server card shape", () => {
    const card = { id: "9", type: "5", type_arg: "7", location: "hand", location_arg: OTHER };
    expect(game.getCardDivLocatonId(card)).to.equal("civilization_holder_" + OTHER);
  });
});

describe("Game.moveCard civilization", () => {
  const civCard = { id: "9", type: "5", type_arg: "7", location: "hand", location_arg: ME };
  const recordCalls = (game) => {
    const calls = [];
    game.placeToken = (...args) => calls.push(["placeToken", ...args]);
    game.setupCivCard = (card, location) => calls.push(["setupCivCard", location]);
    return calls;
  };
  afterEach(() => $("civilization_7")?.remove());

  it("creates a civ card not on the page yet in the player holder", () => {
    const game = makeGame();
    const calls = recordCalls(game);
    game.moveCard({ ...civCard });
    expect(calls).to.deep.equal([["setupCivCard", "civilization_holder_" + ME]]);
  });

  it("moves an existing civ card to the choice area", () => {
    document.body.insertAdjacentHTML("beforeend", "<div id='civilization_7'></div>");
    const game = makeGame();
    const calls = recordCalls(game);
    game.moveCard({ ...civCard, location: "choice" });
    expect(calls).to.deep.equal([["placeToken", "civilization_7", "civilizations"]]);
  });
});
