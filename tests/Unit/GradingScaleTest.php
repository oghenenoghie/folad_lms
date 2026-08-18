<?php

namespace Tests\Unit;

use App\Models\GradingScale;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_band_for_finds_the_correct_grade(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $scale = GradingScale::create(['school_id' => $school->id, 'name' => 'WAEC Standard', 'is_default' => true]);
        $scale->bands()->createMany([
            ['school_id' => $school->id, 'grade' => 'A1', 'min_score' => 75, 'max_score' => 100, 'remark' => 'Excellent'],
            ['school_id' => $school->id, 'grade' => 'B2', 'min_score' => 70, 'max_score' => 74.99, 'remark' => 'Very Good'],
            ['school_id' => $school->id, 'grade' => 'F9', 'min_score' => 0, 'max_score' => 39.99, 'remark' => 'Fail'],
        ]);

        $this->assertSame('A1', $scale->bandFor(80)->grade);
        $this->assertSame('B2', $scale->bandFor(70)->grade);
        $this->assertSame('F9', $scale->bandFor(0)->grade);
        $this->assertNull($scale->bandFor(50));
    }
}
