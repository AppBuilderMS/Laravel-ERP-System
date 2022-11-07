<?php

namespace Database\Factories\ERP\Purchases;

use App\Models\ERP\Inventory\Warehouse;
use App\Models\ERP\Purchases\PurchaseInvoice;
use App\Models\ERP\Purchases\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ERP\Purchases\PurchaseInvoice>
 */
class PurchaseInvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PurchaseInvoice::class;


    private static int $number = 1;
    private static int $full_code = 1;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $inv_number = 'PUR-INV-'. str_pad(self::$full_code++, 5, '0', STR_PAD_LEFT);
        return [
            'sequential_code_id' => 4,
            'number' => self::$number++,
            'inv_number' => $inv_number,
            'issue_date' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
            'due_date' => $this->faker->dateTimeThisMonth('now'),
            'supplier_id' => Supplier::inRandomOrder()->first()->id,
            'subtotal' => 0.00 .'ج.م.',
            'discount' => null,
            'discount_type' => null,
            'discount_inv' => null,
            'warehouse_id' => Warehouse::inRandomOrder()->first()->id,
            'shipping_expense' => null,
            'shipping_expense_inv' => null,
            'total_inv' => 0.00,
            'down_payment' => null,
            'down_payment_type' => null,
            'down_payment_inv' => null,
            'due_amount' => 0.00.' ج.م.',
            'deposit_payment_method' => null,
            'deposit_transaction_id' => null,
            'payment_payment_method' => null,
            'payment_transaction_id' => null,
            'paid_to_supplier_inv' => null,
            'due_amount_after_paid' => null,
            'payments_total' => 0.00,
            'due_amount_after_payments' => 0.00,
            'notes' => null,
            'user_id' => User::inRandomOrder()->first()->id,
            'payment_status' => 1,
            'receiving_status' => 1,
        ];
    }
}
