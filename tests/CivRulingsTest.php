<?php
require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * The "rulings" of a civilization in material are the CIV section of misc/FORMAL_RULES.txt for that
 * civilization, one string per clause, minus the clause codes, the cross-references in parentheses
 * and everything from the first "Ref.", "Ruled by" or "Impl." line on; a clause that starts with
 * "Impl." is not shown at all. Editing either side without the other fails here.
 */
class CivRulingsTest extends PHPUnit\Framework\TestCase {
    const CODE = "[A-Z][A-Z_]*(?:\.[A-Z_]+)*\.\d+";

    function testMaterialRulingsMirrorFormalRules() {
        $game = new GameUT();
        $game->init();
        $game->doAdjustMaterial(2, 8);
        $sections = $this->formalRulesCivSections();
        $this->assertNotEmpty($sections);
        foreach ($sections as $civ => $clauses) {
            $this->assertEquals($clauses, array_get($game->civilizations[constant("CIV_$civ")], "rulings", []), $civ);
        }
    }

    private function formalRulesCivSections(): array {
        $text = file_get_contents(__DIR__ . "/../misc/FORMAL_RULES.txt");
        $text = substr($text, strpos($text, "\nCIV - civilization specific"));
        $sections = [];
        foreach (array_slice(preg_split('/\n(?=CIV\.[A-Z_]+\n)/', $text), 1) as $section) {
            [$name, $body] = explode("\n", $section, 2);
            foreach (preg_split('/\n(?=CIV\.[A-Z_]+\.\d+ )/', trim($body, "\n")) as $clause) {
                $ruling = $this->stripCodes($clause);
                if ($ruling !== "") {
                    $sections[substr($name, strlen("CIV."))][] = $ruling;
                }
            }
        }
        return $sections;
    }

    private function stripCodes(string $clause): string {
        $kept = [];
        foreach (explode("\n", $clause) as $line) {
            if (preg_match("/^\s+(Ref\.|Ruled by|Impl\.)/", $line)) {
                break;
            }
            $kept[] = trim($line);
        }
        $code = self::CODE;
        $text = implode(" ", $kept);
        $text = preg_replace("/^$code /", "", $text);
        if (str_starts_with($text, "Impl. ")) {
            return "";
        }
        $text = preg_replace("/ \((?:$code)(?:, $code)*\)/", "", $text);
        $text = preg_replace("/, $code(?=\))/", "", $text);
        $this->assertDoesNotMatchRegularExpression("/$code/", $text, "a clause code survived stripping");
        return preg_replace("/\s+/", " ", trim($text));
    }
}
