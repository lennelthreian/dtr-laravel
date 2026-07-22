<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class OfficialLsNoReturnTest extends TestCase
{
    /**
     * Exact computation from DtrController.php locator_slip case block (lines 386-432).
     */
    private function computeLocatorSlip(array &$dtrData, int $dayNum, object $edit, array $settings, ?string $existingRemark = null): void
    {
        $r = $existingRemark;
        $lsType = $edit->field ?: 'official';
        $whereabouts = $edit->new_value;
        $timeLeft = $edit->ls_time_left ? date('h:i A', strtotime($edit->ls_time_left)) : '';
        $timeReturned = $edit->ls_no_return ? 'No Return' : ($edit->ls_time_returned ? date('h:i A', strtotime($edit->ls_time_returned)) : '');
        $dtrData[$dayNum]['has_punch'] = true;
        $details = array_filter([$whereabouts, $timeLeft ? "Left: $timeLeft" : null, $timeReturned ? "Ret: $timeReturned" : null]);
        if ($lsType === 'personal') {
            $lsDuration = 0;
            if ($edit->ls_time_left && $edit->ls_time_returned && !$edit->ls_no_return) {
                $lsDuration = (strtotime($edit->ls_time_returned) - strtotime($edit->ls_time_left)) / 60;
            } elseif ($edit->ls_no_return && $edit->ls_time_left) {
                $pmEnd = $settings['pm_end'] ?? '17:00';
                $timeLeftTs = strtotime($edit->ls_time_left);
                $pmEndTs = strtotime($pmEnd);
                $lsDuration = ($pmEndTs - $timeLeftTs) / 60;
            }
            $existingTotal = $dtrData[$dayNum]['total_hours'] ?? '00:00';
            $parts = explode(':', $existingTotal);
            $existingMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
            $remainingMins = max(0, $existingMins - $lsDuration);
            $hours = floor($remainingMins / 60);
            $mins = round($remainingMins % 60);
            $dtrData[$dayNum]['total_hours'] = sprintf('%02d:%02d', $hours, $mins);
            $lsRemark = 'LS: ' . implode(' | ', $details) . ' (Personal)';
            if ($lsDuration > 0) {
                $lsRemark .= ' | LS Deduction: ' . gmdate('H:i', $lsDuration * 60);
            }
            $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . $lsRemark : $lsRemark;
        } else {
            $lsRemark = 'LS: ' . implode(' | ', $details) . ' (Official)';
            $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . $lsRemark : $lsRemark;
            if ($edit->ls_no_return && $edit->ls_time_left) {
                $pmEnd = $settings['pm_end'] ?? '17:00';
                $timeLeftTs = strtotime($edit->ls_time_left);
                $pmEndTs = strtotime($pmEnd);
                $remainingMins = max(0, ($pmEndTs - $timeLeftTs) / 60);
                $existingTotal = $dtrData[$dayNum]['total_hours'] ?? '00:00';
                $parts = explode(':', $existingTotal);
                $existingMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                $totalMins = $existingMins + $remainingMins;
                $hours = floor($totalMins / 60);
                $mins = round($totalMins % 60);
                $dtrData[$dayNum]['total_hours'] = sprintf('%02d:%02d', $hours, $mins);
            }
        }
    }

    /**
     * Exact UT exclusion logic from DtrController.php (lines 559-573).
     */
    private function computeUtForDay(array &$day, string $empDefaultWW = 'regular'): void
    {
        $ww = $day['work_week_type'] ?? $empDefaultWW;
        $expectedMins = $ww === '4-day' ? 600 : 480;
        $totalMins = 0;
        if (!empty($day['total_hours'])) {
            $parts = explode(':', $day['total_hours']);
            $totalMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
        }
        $isOfficialLs = strpos($day['remarks'] ?? '', 'LS:') !== false && strpos($day['remarks'] ?? '', '(Official)') !== false;
        $utMins = (!empty($day['so_number']) || !empty($day['to_number']) || !empty($day['ob_number']) || !empty($day['is_holiday']) || !empty($day['is_work_suspension']) || $isOfficialLs) ? 0 : ($totalMins > 0 ? max(0, $expectedMins - $totalMins) : 0);
        $remarks = trim(preg_replace('/(?:^|\s*\|\s*)UT:\s*\d+:\d+/', '', $day['remarks'] ?? ''), ' |');
        $parts = [];
        if ($remarks !== '') $parts[] = $remarks;
        if ($utMins > 0) $parts[] = 'UT: ' . gmdate('H:i', $utMins * 60);
        $day['remarks'] = implode(' | ', $parts);
    }

    private function makeEdit(array $overrides = []): object
    {
        return (object) array_merge([
            'type' => 'locator_slip',
            'field' => 'official',
            'new_value' => 'Field visit',
            'ls_time_left' => null,
            'ls_time_returned' => null,
            'ls_no_return' => false,
        ], $overrides);
    }

    public function test_no_return_adds_remaining_hours_to_total(): void
    {
        $dtrData = [9 => ['total_hours' => '08:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '15:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('10:00', $dtrData[9]['total_hours']);
    }

    public function test_no_return_with_zero_existing_hours(): void
    {
        $dtrData = [9 => ['total_hours' => '00:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '13:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('04:00', $dtrData[9]['total_hours']);
    }

    public function test_no_return_full_day_remaining(): void
    {
        $dtrData = [9 => ['total_hours' => '00:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '08:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('09:00', $dtrData[9]['total_hours']);
    }

    public function test_no_return_remark_format(): void
    {
        $dtrData = [9 => ['total_hours' => '08:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit([
            'new_value' => 'Meeting at client office',
            'ls_time_left' => '14:00',
            'ls_no_return' => true,
        ]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertStringContainsString('LS: Meeting at client office', $dtrData[9]['remarks']);
        $this->assertStringContainsString('Left: 02:00 PM', $dtrData[9]['remarks']);
        $this->assertStringContainsString('No Return', $dtrData[9]['remarks']);
        $this->assertStringContainsString('(Official)', $dtrData[9]['remarks']);
    }

    public function test_no_return_remark_with_existing_remark(): void
    {
        $dtrData = [9 => ['total_hours' => '08:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['new_value' => 'Field visit', 'ls_time_left' => '15:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, [], 'Holiday');

        $this->assertStringStartsWith('Holiday | ', $dtrData[9]['remarks']);
        $this->assertStringContainsString('(Official)', $dtrData[9]['remarks']);
    }

    public function test_no_return_with_custom_pm_end(): void
    {
        $dtrData = [9 => ['total_hours' => '00:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '14:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, ['pm_end' => '16:00']);

        $this->assertEquals('02:00', $dtrData[9]['total_hours']);
    }

    public function test_no_return_left_after_pm_end(): void
    {
        $dtrData = [9 => ['total_hours' => '08:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '18:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('08:00', $dtrData[9]['total_hours']);
    }

    public function test_no_return_left_exactly_pm_end(): void
    {
        $dtrData = [9 => ['total_hours' => '08:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '17:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('08:00', $dtrData[9]['total_hours']);
    }

    public function test_no_return_has_punch_set_true(): void
    {
        $dtrData = [9 => ['total_hours' => '08:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '15:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertTrue($dtrData[9]['has_punch']);
    }

    public function test_no_return_default_pm_end_fallback(): void
    {
        $dtrData = [9 => ['total_hours' => '00:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '16:30', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('00:30', $dtrData[9]['total_hours']);
    }

    public function test_no_return_with_minutes_rounding(): void
    {
        $dtrData = [9 => ['total_hours' => '07:45', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '15:20', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('09:25', $dtrData[9]['total_hours']);
    }

    public function test_no_return_ls_type_defaults_to_official(): void
    {
        $dtrData = [9 => ['total_hours' => '08:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['field' => null, 'ls_time_left' => '15:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertStringContainsString('(Official)', $dtrData[9]['remarks']);
        $this->assertEquals('10:00', $dtrData[9]['total_hours']);
    }

    public function test_no_return_missing_existing_total_defaults_to_zero(): void
    {
        $dtrData = [9 => ['remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit(['ls_time_left' => '14:00', 'ls_no_return' => true]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('03:00', $dtrData[9]['total_hours']);
    }

    public function test_official_ls_no_return_excluded_from_ut(): void
    {
        $day = [
            'total_hours' => '04:00',
            'remarks' => 'LS: Baguio City | Left: 01:00 PM | No Return (Official)',
            'has_punch' => true,
        ];
        $this->computeUtForDay($day);
        $this->assertStringNotContainsString('UT:', $day['remarks']);
    }

    public function test_personal_ls_no_return_still_has_ut(): void
    {
        $day = [
            'total_hours' => '04:00',
            'remarks' => 'LS: Baguio City | Left: 01:00 PM | No Return (Personal) | LS Deduction: 04:00',
            'has_punch' => true,
        ];
        $this->computeUtForDay($day);
        $this->assertStringContainsString('UT:', $day['remarks']);
    }

    public function test_official_ls_with_return_excluded_from_ut(): void
    {
        $day = [
            'total_hours' => '06:00',
            'remarks' => 'LS: Meeting | Left: 02:00 PM | Ret: 04:00 PM (Official)',
            'has_punch' => true,
        ];
        $this->computeUtForDay($day);
        $this->assertStringNotContainsString('UT:', $day['remarks']);
    }

    public function test_non_ls_day_with_low_hours_gets_ut(): void
    {
        $day = [
            'total_hours' => '06:00',
            'remarks' => '',
            'has_punch' => true,
        ];
        $this->computeUtForDay($day);
        $this->assertStringContainsString('UT: 02:00', $day['remarks']);
    }

    public function test_official_ls_no_ut_regardless_of_total_hours(): void
    {
        $day = [
            'total_hours' => '02:00',
            'remarks' => 'LS: City Hall | Left: 03:00 PM | No Return (Official)',
            'has_punch' => true,
        ];
        $this->computeUtForDay($day);
        $this->assertStringNotContainsString('UT:', $day['remarks']);
    }

    public function test_official_ls_4day_workweek_no_ut(): void
    {
        $day = [
            'total_hours' => '06:00',
            'work_week_type' => '4-day',
            'remarks' => 'LS: Training | Left: 11:00 AM | No Return (Official)',
            'has_punch' => true,
        ];
        $this->computeUtForDay($day);
        $this->assertStringNotContainsString('UT:', $day['remarks']);
    }

    public function test_gabon_july9_no_ut_scenario(): void
    {
        $dtrData = [9 => ['total_hours' => '00:00', 'remarks' => '', 'has_punch' => false]];
        $edit = $this->makeEdit([
            'new_value' => 'Baguio City',
            'ls_time_left' => '13:00',
            'ls_no_return' => true,
        ]);

        $this->computeLocatorSlip($dtrData, 9, $edit, []);

        $this->assertEquals('04:00', $dtrData[9]['total_hours']);
        $this->assertStringContainsString('No Return', $dtrData[9]['remarks']);
        $this->assertStringContainsString('(Official)', $dtrData[9]['remarks']);

        $this->computeUtForDay($dtrData[9]);
        $this->assertStringNotContainsString('UT:', $dtrData[9]['remarks']);
    }
}
