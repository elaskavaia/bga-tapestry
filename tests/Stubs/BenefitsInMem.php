<?php

declare(strict_types=1);

/**
 * In memory model of the benefit table, the effect stack of the game.
 *
 * The framework stubs run no SQL, so every DB accessor of that table is served from here instead.
 * Rows carry the real column names and pop in the "ORDER BY benefit_prerequisite, benefit_id"
 * order that stBenefitManager relies on.
 */
class BenefitsInMem {
    /** rows keyed by benefit_id */
    public array $rows = [];
    private int $next_id = 1;

    function insert($category, $type, $prerequisite, $quantity, $data, $player_id): int {
        $id = $this->next_id++;
        $this->rows[$id] = [
            "benefit_id" => $id,
            "benefit_category" => $category,
            "benefit_type" => (int) $type,
            "benefit_prerequisite" => (int) $prerequisite,
            "benefit_quantity" => (int) $quantity,
            "benefit_data" => $data,
            "benefit_player_id" => (int) $player_id,
        ];
        return $id;
    }

    function delete($id): void {
        unset($this->rows[(int) $id]);
    }

    function deleteOfPlayer($player_id): void {
        foreach ($this->rows as $id => $row) {
            if ($row["benefit_player_id"] == $player_id) {
                unset($this->rows[$id]);
            }
        }
    }

    function set($id, string $field, $value): void {
        $id = (int) $id;
        if (isset($this->rows[$id])) {
            $this->rows[$id][$field] = $value;
        }
    }

    function bumpPrerequisites(): void {
        foreach ($this->rows as $id => $row) {
            $this->rows[$id]["benefit_prerequisite"] = $row["benefit_prerequisite"] + 1;
        }
    }

    function byId($id): ?array {
        return $this->rows[(int) $id] ?? null;
    }

    /** All rows in pop order, keyed by benefit_id. */
    function all(): array {
        $rows = $this->rows;
        uasort($rows, fn($a, $b) => [$a["benefit_prerequisite"], $a["benefit_id"]] <=> [$b["benefit_prerequisite"], $b["benefit_id"]]);
        return $rows;
    }

    /** Rows in pop order matching every given benefit_* column, keyed by benefit_id. */
    function search(array $filter): array {
        return array_filter($this->all(), function ($row) use ($filter) {
            foreach ($filter as $field => $value) {
                if ($row[$field] != $value) {
                    return false;
                }
            }
            return true;
        });
    }

    function first(array $filter = []): ?array {
        $rows = $this->search($filter);
        return $rows ? reset($rows) : null;
    }
}
