<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BiometricPunchController extends Controller
{
    public function webhook(Request $request)
    {
        $punchTime = $request->input('PunchTime', $request->input('punch_time', now()));
        $punchState = $request->input('PunchState', $request->input('punch_state', '0'));
        $verifyType = $request->input('VerifyType', $request->input('verify_type', null));
        $terminalSn = $request->input('SN', $request->input('terminal_sn', ''));
        $terminalAlias = $request->input('terminal_alias', '');

        $punchTimestamp = $punchTime instanceof Carbon
            ? $punchTime
            : Carbon::parse($punchTime);

        $empCode = $request->input('emp_code', $request->input('PIN', $request->input('bio_id', $request->input('employee_code', ''))));

        $pushedToZk = $this->pushPunchToZktecoDatabase($empCode, $punchTimestamp, $punchState, $verifyType, $terminalSn, $terminalAlias);

        DB::table('punch_sync_logs')->insert([
            'emp_code' => $empCode ?: null,
            'punch_time' => $punchTimestamp,
            'punch_state' => $punchState,
            'terminal_sn' => $terminalSn,
            'terminal_alias' => $terminalAlias,
            'source' => 'webhook',
            'sync_status' => $pushedToZk ? 'synced' : 'failed',
            'sync_message' => $pushedToZk
                ? 'Real-time push from biometric device and stored in zkbiotime'
                : 'Real-time push from biometric device (failed to write to zkbiotime)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response('OK', 200);
    }

    private function pushPunchToZktecoDatabase($empCode, Carbon $punchTime, $punchState, $verifyType, $terminalSn, $terminalAlias): bool
    {
        $empCode = trim((string) $empCode);

        if ($empCode === '') {
            return false;
        }

        try {
            $connection = DB::connection('zkbiotime');

            $attributes = [
                'emp_code' => $empCode,
                'punch_time' => $punchTime,
                'punch_state' => (string) $punchState,
                'terminal_sn' => $terminalSn ?: null,
            ];

            if ($connection->table('iclock_transaction')->where($attributes)->exists()) {
                return true;
            }

            $connection->table('iclock_transaction')->insert($attributes + [
                'verify_type' => $verifyType !== null ? (int) $verifyType : 15,
                'work_code' => '0',
                'terminal_alias' => $terminalAlias ?: null,
                'source' => 1,
                'purpose' => 9,
                'is_attendance' => 1,
                'upload_time' => now(),
                'sync_status' => 0,
                'is_mask' => 0,
                'temperature' => 0.0,
                'emp_id' => $connection->table('personnel_employee')->where('emp_code', $empCode)->value('id'),
                'terminal_id' => $terminalSn
                    ? $connection->table('iclock_terminal')->where('sn', $terminalSn)->value('id')
                    : null,
                'company_code' => '1',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to push biometric punch to zkbiotime database: ' . $e->getMessage());

            return false;
        }
    }

    public function status(Request $request)
    {
        $lastSync = DB::table('sync_progress')
            ->where('sync_type', 'iclock_transaction')
            ->first();

        $pendingWebhooks = DB::table('punch_sync_logs')
            ->where('source', 'webhook')
            ->count();

        $totalSynced = DB::table('punch_sync_logs')->count();

        return response()->json([
            'last_synced_id' => $lastSync ? $lastSync->last_synced_id : 0,
            'last_sync_at' => $lastSync ? $lastSync->last_sync_at : null,
            'total_synced' => $totalSynced,
            'webhook_punches' => $pendingWebhooks,
        ]);
    }
}
