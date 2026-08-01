<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->boolean('unlock_requested')->default(false)->after('is_locked');
            $table->text('unlock_reason')->nullable()->after('unlock_requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->after('unlock_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropColumn(['unlock_requested', 'unlock_reason', 'requested_by']);
        });
    }
};