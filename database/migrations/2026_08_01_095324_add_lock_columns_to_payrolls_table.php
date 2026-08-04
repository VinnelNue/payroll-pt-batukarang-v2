<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('status');
            }
            if (!Schema::hasColumn('payrolls', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('is_locked');
            }
            if (!Schema::hasColumn('payrolls', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete()->after('locked_at');
            }
            if (!Schema::hasColumn('payrolls', 'unlock_requested')) {
                $table->boolean('unlock_requested')->default(false)->after('locked_by');
            }
            if (!Schema::hasColumn('payrolls', 'unlock_reason')) {
                $table->text('unlock_reason')->nullable()->after('unlock_requested');
            }
            if (!Schema::hasColumn('payrolls', 'requested_by')) {
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->after('unlock_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropForeign(['requested_by']);
            $table->dropColumn(['is_locked', 'locked_at', 'locked_by', 'unlock_requested', 'unlock_reason', 'requested_by']);
        });
    }
};