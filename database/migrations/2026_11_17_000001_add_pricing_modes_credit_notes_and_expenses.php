<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How a care category is priced: 'item' (one price per care item),
        // 'category' (one flat price per session, whatever the number of
        // items used) or 'none' (never billed).
        Schema::table('care_categories', function (Blueprint $table) {
            $table->string('pricing_mode', 10)->default('item')->after('order');
        });

        // Categories already seeded: verses are never billed, cupping is
        // one flat price per session.
        DB::table('care_categories')->where('code', 'verse')->update(['pricing_mode' => 'none']);
        DB::table('care_categories')->where('code', 'cupping')->update(['pricing_mode' => 'category']);

        Schema::create('center_care_category_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('care_category_id')->constrained('care_categories')->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->unique(['center_id', 'care_category_id']);
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->decimal('credited_total', 12, 2)->default(0)->after('paid_total'); // cache, recomputed from credit notes
        });

        // A credit note never edits the invoice it corrects: it lowers
        // what is due on it. A refund is a negative row in billing_payments.
        Schema::create('billing_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->string('number', 20);
            $table->decimal('amount', 12, 2);
            $table->date('issued_on');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['center_id', 'number']);
            $table->index(['center_id', 'issued_on']);
        });

        // One counter per center, document type and year, read under a
        // row lock: numbers stay gapless and unique under concurrency.
        Schema::create('billing_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['center_id', 'type', 'year']);
        });

        Schema::create('billing_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('category_option_id')->nullable()->constrained('enum_options')->nullOnDelete();
            $table->string('label');
            $table->string('supplier')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->date('spent_on');
            $table->string('method', 20);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['center_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_expenses');
        Schema::dropIfExists('billing_sequences');
        Schema::dropIfExists('billing_credit_notes');
        Schema::table('billing_invoices', fn (Blueprint $table) => $table->dropColumn('credited_total'));
        Schema::dropIfExists('center_care_category_prices');
        Schema::table('care_categories', fn (Blueprint $table) => $table->dropColumn('pricing_mode'));
    }
};
