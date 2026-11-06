<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('total_discount', 10, 2)->default(0);
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->decimal('discount_percent', 4, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('total_discount');
        });
    }
};
