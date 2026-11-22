import { makeBga } from "./setup.js";
import { expect } from "chai";
import { Game } from "../../modules/js/Game.js";

const ME = 10;
const WORKER = 2;
const COIN = 0;

describe("Game.onDieClick outside choose_die (BGA #226930 / #242269)", () => {
  let random;
  beforeEach(() => {
    random = Math.random;
    Object.assign(dojo, {
      removeClass: (id, className) => $(id).classList.remove(className),
      setAttr: (id, name, value) => $(id).setAttribute(name, value)
    });
    document.getElementById("black_die")?.remove();
    document.body.insertAdjacentHTML("beforeend", `<div id="black_die" data-num="${WORKER}"></div>`);
  });
  afterEach(() => (Math.random = random));

  // pins buggy behavior: the Alchemists keep prompt (civTokenAdvance) is not choose_die, so a board click
  // repaints the die to a random face the server never rolled while the buttons keep the real one; flips when fixed
  it("repaints the board die away from the face the keep buttons and the server use", () => {
    const game = new Game(makeBga({ players: { [ME]: { id: ME, name: "Alice", color: "ff0000" } }, dice: { black: WORKER } }, ME));
    game.bga.actions = { checkPossibleActions: (action) => action == "civTokenAdvance" };
    game.showHelp = () => false;
    game.updateDieTooltip = () => "Coin";
    game.isInstantaneousMode = () => true;
    Math.random = () => COIN / 6;

    game.onDieClick({ currentTarget: $("black_die") });

    expect($("black_die").getAttribute("data-num")).to.equal(String(COIN));
    expect(game.gamedatas.dice.black).to.equal(WORKER);
    expect(game.getConqDieDiv("black")).to.contain(`die-face-${WORKER}`);
  });
});
