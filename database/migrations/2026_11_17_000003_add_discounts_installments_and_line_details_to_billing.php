<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_invoice_lines', function (Blueprint $table) {
            $table->text('description')->nullable()->after('label');
            // Set on a flat-priced category line (cupping...).
            $table->foreignId('care_category_id')->nullable()->after('care_item_id')->constrained('care_categories')->nullOnDelete();
        });

        // Lines always keep their real price: a discount (social case,
        // goodwill...) is carried by the invoice. `total` stays the NET
        // to pay (subtotal - discount), so nothing else has to change.
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->after('issued_on');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('discount_amount'); // set when given as a %
            $table->foreignId('discount_reason_option_id')->nullable()->after('discount_percent')->constrained('enum_options')->nullOnDelete();
            $table->string('discount_note')->nullable()->after('discount_reason_option_id');
        });

        DB::table('billing_invoices')->update(['subtotal' => DB::raw('total')]);

        // Payment schedule. No status and no payment link: what is paid
        // on the invoice is allocated to the oldest instalment first,
        // each instalment's state is derived (Invoice::installmentsWithStatus()).
        Schema::create('billing_invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->date('due_on');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->index(['invoice_id', 'due_on']);
        });

        // Standing discount of a patient (social rate), pre-applied to
        // each new invoice.
        Schema::table('patients', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->foreignId('discount_reason_option_id')->nullable()->constrained('enum_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_reason_option_id');
            $table->dropColumn('discount_percent');
        });
        Schema::dropIfExists('billing_invoice_installments');
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_reason_option_id');
            $table->dropColumn(['subtotal', 'discount_amount', 'discount_percent', 'discount_note']);
        });
        Schema::table('billing_invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('care_category_id');
            $table->dropColumn('description');
        });
    }
};
