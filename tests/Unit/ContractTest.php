<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\SnapshotRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\ContractSchema;

final class ContractTest extends TestCase
{
    public function testEveryReadColumnExistsInContractWithSameType(): void
    {
        $contract = ContractSchema::columnsOrSkip($this);

        foreach (SnapshotRepository::COLUMNS as $name => $type) {
            $this->assertArrayHasKey($name, $contract, "Column '$name' is not in contract/schema.json");
            $this->assertSame($contract[$name], $type, "Column '$name' type differs from the contract");
        }
    }

    public function testTableNameMatchesContract(): void
    {
        ContractSchema::columnsOrSkip($this);

        $this->assertSame(ContractSchema::TABLE, SnapshotRepository::TABLE);
    }
}
