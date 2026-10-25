<?php
declare(strict_types=1);

require __DIR__ . '/../src/Money.php';

// No float errors: 19.99 + 0.01 is exactly 20.00, not 20.000000000004.
$total = Money::fromMajor('19.99', 'USD')->add(Money::fromMajor('0.01', 'USD'));
echo $total->format(), PHP_EOL;                    // $20.00
echo $total->minor(), ' minor units', PHP_EOL;     // 2000 minor units

$price = Money::fromMajor('49.99', 'PKR');
echo $price->multiply(3)->format(), PHP_EOL;       // Rs 149.97
echo $price->subtract(Money::fromMajor('10.00', 'PKR'))->format(), PHP_EOL; // Rs 39.99

$yen = Money::fromMajor('1500', 'JPY');
echo $yen->format(), PHP_EOL;                      // ¥1,500 (zero-decimal currency)

try {
    Money::fromMajor('10', 'USD')->add(Money::fromMajor('10', 'EUR'));
} catch (InvalidArgumentException $e) {
    echo 'Blocked: ', $e->getMessage(), PHP_EOL;   // Cannot combine USD with EUR
}
