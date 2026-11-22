import { makeBga } from "./setup.js";
import { expect } from "chai";
import { Game } from "../../modules/js/Game.js";

const ME = 10;
const WORKER = 2;
const COIN = 0;
const BLACK_SPOT = 1;
const CIV_ALCHEMISTS = 1;

describe("Game.onDieClick outside choose_die (BGA #226930 / #242269)", () => {
  let random;
  let game;
  let clicks;
  beforeEach(() => {
    random = Math.random;
    Object.assign(dojo, {
      removeClass: (id, className) => $(id).classList.remove(className),
      setAttr: (id, name, value) => $(id).setAttribute(name, value)
    });
    document.getElementById("black_die")?.remove();
    document.getElementById(`button_${BLACK_SPOT}`)?.remove();
    document.body.insertAdjacentHTML("beforeend", `<div id="black_die" data-num="${WORKER}"></div><div id="button_${BLACK_SPOT}"></div>`);
    clicks = 0;
    $(`button_${BLACK_SPOT}`).addEventListener("click", () => clicks++);
    game = new Game(makeBga({ players: { [ME]: { id: ME, name: "Alice", color: "ff0000" } }, dice: { black: WORKER } }, ME));
    game.bga.actions = { checkPossibleActions: (action) => action == "civTokenAdvance" };
    game.CON = { CIV_ALCHEMISTS };
    game.gamedatas.gamestate = { args: {} };
    game.showHelp = () => false;
    game.updateDieTooltip = () => "Coin";
    game.isInstantaneousMode = () => true;
    Math.random = () => COIN / 6;
  });
  afterEach(() => (Math.random = random));

  function alchemistsPrompt(slots_choice) {
    game.selectedCiv = CIV_ALCHEMISTS;
    game.gamedatas.gamestate = { args: { benefits: { 7: { benefit_type: CIV_ALCHEMISTS, slots_choice } } } };
  }

  it("acts as the keep button during the Alchemists keep prompt and leaves the die face alone", () => {
    alchemistsPrompt({ [BLACK_SPOT]: {}, 2: {}, 3: {} });

    game.onDieClick({ currentTarget: $("black_die") });

    expect(clicks).to.equal(1);
    expect($("black_die").getAttribute("data-num")).to.equal(String(WORKER));
  });

  const ignored = {
    "a die already kept": () => alchemistsPrompt({ 2: {}, 3: {} }),
    "any die in the roll all dice step": () => alchemistsPrompt([{ title: "Roll" }]),
    "a die while another pending civ is selected": () => {
      alchemistsPrompt({ [BLACK_SPOT]: {}, 2: {}, 3: {} });
      game.selectedCiv = 2;
    }
  };
  for (const [name, prompt] of Object.entries(ignored)) {
    it(`ignores ${name} during the Alchemists keep prompt`, () => {
      prompt();

      game.onDieClick({ currentTarget: $("black_die") });

      expect(clicks).to.equal(0);
      expect($("black_die").getAttribute("data-num")).to.equal(String(WORKER));
    });
  }

  it("still rolls for fun when the player has no civ ability to resolve", () => {
    alchemistsPrompt({ [BLACK_SPOT]: {}, 2: {}, 3: {} });
    game.bga.actions = { checkPossibleActions: () => false };

    game.onDieClick({ currentTarget: $("black_die") });

    expect(clicks).to.equal(0);
    expect($("black_die").getAttribute("data-num")).to.equal(String(COIN));
  });

  it("still rolls for fun outside the Alchemists keep prompt", () => {
    game.selectedCiv = 0;

    game.onDieClick({ currentTarget: $("black_die") });

    expect(clicks).to.equal(0);
    expect($("black_die").getAttribute("data-num")).to.equal(String(COIN));
    expect(game.gamedatas.dice.black).to.equal(WORKER);
  });
});
