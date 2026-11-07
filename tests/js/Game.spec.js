import { makeBga } from "./setup.js";
import { expect } from "chai";
import { Game } from "../../modules/js/Game.js";
import { TapAntiStock } from "../../modules/js/TapAntiStock.js";

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

describe("Game action button helpers", () => {
  beforeEach(() => $("generalactions").replaceChildren());

  const buttons = () => [...$("generalactions").children];

  it("adds an image button with the default color, shadow and tooltip", () => {
    let clicked = null;
    const button = makeGame().addImageActionButton(
      "button_die",
      "<div class='die'></div>",
      (event) => (clicked = event.currentTarget.id),
      undefined,
      "Roll"
    );
    expect(button.id).to.equal("button_die");
    expect(button.dataset.color).to.equal("secondary");
    expect(button.classList.contains("shadow")).to.be.true;
    expect(button.classList.contains("bgaimagebutton")).to.be.true;
    expect(button.getAttribute("title")).to.equal("Roll");
    button.click();
    expect(clicked).to.equal("button_die");
  });

  it("adds a player button with the player name and color", () => {
    const button = makeGame().addPlayerActionButton(OTHER, () => {}, "button_historian_");
    expect(button.id).to.equal("button_historian_" + OTHER);
    expect(button.innerHTML).to.equal("Bob");
    expect(button.style.getPropertyValue("color")).to.equal("rgb(0, 0, 255)");
    expect(button.style.getPropertyValue("background-color")).to.equal("");
  });

  it("adds a player button with the player background color", () => {
    const game = makeGame();
    game.gamedatas.players[OTHER].color_back = "bbbbbb";
    const button = game.addPlayerActionButton(OTHER, () => {});
    expect(button.style.getPropertyValue("background-color")).to.equal("rgb(187, 187, 187)");
  });

  it("adds no player button for an unknown player", () => {
    expect(makeGame().addPlayerActionButton(99, () => {})).to.be.undefined;
    expect(buttons()).to.be.empty;
  });

  it("keeps a single cancel button, which cancels the local state by default", () => {
    const game = makeGame();
    let cancelled = false;
    game.cancelLocalStateEffects = () => (cancelled = true);
    game.addCancelButton("Back", () => {});
    game.addCancelButton();
    expect(buttons().map((button) => button.id)).to.deep.equal(["button_cancel"]);
    expect($("button_cancel").innerHTML).to.equal("Cancel");
    $("button_cancel").click();
    expect(cancelled).to.be.true;
  });

  it("adds a track slot button, with a suffix when the id is taken", () => {
    const game = makeGame();
    game.tech_track_types = { 1: { description: "Exploration" } };
    game.tech_track_data = { 1: { 3: { name: "Scout" } } };
    game.addTrackSlotActionButton("1_3", "button_benefit", () => {});
    game.addTrackSlotActionButton("1_3", "button_benefit", () => {});
    expect(buttons().map((button) => button.id)).to.deep.equal(["button_benefit_1_3", "button_benefit_1_3_x"]);
    expect($("button_benefit_1_3").innerHTML).to.contain("Scout/3");
    expect($("button_benefit_1_3").classList.contains("bgaimagebutton")).to.be.true;
  });
});

describe("Game.setupCardsman", () => {
  const setup = () => {
    const game = makeGame();
    const calls = [];
    game.placeToken = (...args) => calls.push(args);
    game.setupCardsman();
    return { game, calls };
  };
  afterEach(() => $("plain_parent")?.remove());

  it("slides each card type stock and its per player fork through placeToken", () => {
    const { game, calls } = setup();
    for (const type of [TERRITORY, SPACE, TAPESTRY]) {
      game.cardsman[type].slide("card_1", "place");
      game.cardsman[type].fork().slide("card_2", "place");
    }
    expect(calls).to.deep.equal(
      Array(3)
        .fill([
          ["card_1", "place"],
          ["card_2", "place"]
        ])
        .flat()
    );
  });

  it("leaves other antistocks on plain placement", () => {
    const { calls } = setup();
    document.body.insertAdjacentHTML("beforeend", "<div id='plain_parent'></div><div id='plain_card'></div>");
    new TapAntiStock("territory").slide("plain_card", "plain_parent");
    expect(calls).to.be.empty;
    expect($("plain_card").parentNode.id).to.equal("plain_parent");
  });
});
