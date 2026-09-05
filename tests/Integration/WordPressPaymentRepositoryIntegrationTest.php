<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests\Integration;

use BrifnetMpesa\Database\SchemaExecutor;
use BrifnetMpesa\Database\WordPressSchema;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\PaymentStatus;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\Tests\MySqlWordPressDatabase;
use BrifnetMpesa\WordPress\WordPressPaymentRepository;
use PDO;
use BrifnetMpesa\Api\DateTimeProvider;
use PHPUnit\Framework\TestCase;

final class WordPressPaymentRepositoryIntegrationTest extends TestCase
{
    private PDO $pdo;

    private MySqlWordPressDatabase $database;

    private string $tableName = 'wp_brifnet_mpesa_transactions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=brifnet_mpesa_test',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );

        $this->database = new MySqlWordPressDatabase(
            $this->pdo
        );

        $this->pdo->exec(
            "DROP TABLE IF EXISTS {$this->tableName}"
        );

        $executor = new class ($this->pdo) implements SchemaExecutor {
            public function __construct(
                private readonly PDO $pdo,
            ) {
            }

            public function execute(string $sql): void
            {
                $this->pdo->exec($sql);
            }
        };

        $schema = new WordPressSchema(
            database: $this->database,
            executor: $executor,
            tableName: $this->tableName,
        );

        $schema->install();
    }

    public function testPaymentCanBePersistedAndRetrievedFromRealDatabase(): void
    {
        $repository = new WordPressPaymentRepository(
            database: $this->database,
            tableName: $this->tableName,
            dateTimeProvider: new IntegrationDateTimeProvider(),
        );

        $payment = new Payment(
            reference: new PaymentReference('INT-1001'),
            phone: new PhoneNumber('0712345678'),
            amount: 500,
            channel: PaymentChannel::STK,
        );

        $repository->save($payment);

        $stored = $repository->findByCheckoutRequestId(
            'non-existent-checkout-id'
        );

        $this->assertNull($stored);

        $row = $this->pdo->query(
            "SELECT
                reference,
                phone,
                amount,
                status,
                payment_channel,
                created_at,
                updated_at
            FROM {$this->tableName}
            WHERE reference = 'INT-1001'"
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);

        $this->assertSame('INT-1001', $row['reference']);
        $this->assertSame('0712345678', $row['phone']);
        $this->assertSame('500.00', $row['amount']);
        $this->assertSame(
            PaymentStatus::PENDING->value,
            $row['status']
        );
        $this->assertSame(
            PaymentChannel::STK->value,
            $row['payment_channel']
        );

        $this->assertNotEmpty($row['created_at']);

        $this->assertNotEmpty($row['updated_at']);
    }

    public function testPaymentCanBeUpdatedAndRetrievedFromRealDatabase(): void
    {
        $repository = new WordPressPaymentRepository(
            database: $this->database,
            tableName: $this->tableName,
            dateTimeProvider: new IntegrationDateTimeProvider(),
        );

        $payment = new Payment(
            reference: new PaymentReference('INT-1002'),
            phone: new PhoneNumber('0712345678'),
            amount: 750,
            channel: PaymentChannel::STK,
        );

        $repository->save($payment);

        $payment = new Payment(
            reference: new PaymentReference('INT-1002'),
            phone: new PhoneNumber('0712345678'),
            amount: 750,
            channel: PaymentChannel::STK,
            status: PaymentStatus::COMPLETED,
            checkoutRequestId: 'ws_CO_123456',
            transactionId: 'NLJ123456789',
        );

        $repository->update($payment);

        $stored = $repository->findByCheckoutRequestId(
            'ws_CO_123456'
        );

        $this->assertNotNull($stored);

        $this->assertSame(
            'INT-1002',
            $stored->reference->value
        );

        $this->assertSame(
            '0712345678',
            $stored->phone->value
        );

        $this->assertSame(
            750,
            $stored->amount
        );

        $this->assertSame(
            PaymentStatus::COMPLETED,
            $stored->status
        );

        $this->assertSame(
            PaymentChannel::STK,
            $stored->channel
        );

        $this->assertSame(
            'ws_CO_123456',
            $stored->checkoutRequestId
        );

        $this->assertSame(
            'NLJ123456789',
            $stored->transactionId
        );
    }
}

final class IntegrationDateTimeProvider implements DateTimeProvider
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            '2026-09-04 15:00:00',
            new \DateTimeZone('Africa/Nairobi')
        );
    }
}