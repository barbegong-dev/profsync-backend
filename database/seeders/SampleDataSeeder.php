<?php

namespace Database\Seeders;

use App\Models\Submission;
use App\Models\User;
use App\Models\Requirement;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    /**
     * Create sample submissions for testing.
     * 
     * Creates 3 sets of sample data:
     * 1. Faculty 1 (CS) - 4 submissions: 2 approved, 1 pending, 1 rejected
     * 2. Faculty 2 (IT) - 3 submissions: 1 approved, 2 pending
     * 3. Legacy Faculty - 2 submissions: all pending
     */
    public function run(): void
    {
        // Get users and requirements
        $faculty1 = User::where('email', 'faculty.cs@etrack.com')->first();
        $faculty2 = User::where('email', 'faculty.it@etrack.com')->first();
        $legacyFaculty = User::where('email', 'faculty@etrack.com')->first();
        $requirements = Requirement::all();

        if (!$faculty1 || !$faculty2 || !$legacyFaculty || $requirements->isEmpty()) {
            $this->command->warn('Required users or requirements not found. Run DatabaseSeeder first.');
            return;
        }

        $this->command->info('Creating sample submissions...');

        // ========================================
        // Faculty 1 (CS Department) - 4 submissions
        // ========================================
        
        // Course Syllabus - Approved
        Submission::firstOrCreate(
            ['faculty_id' => $faculty1->id, 'requirement_id' => $requirements[0]->id],
            [
                'file_path' => 'submissions/sample_syllabus_cs.pdf',
                'status' => 'approved',
                'remarks' => 'Well-structured syllabus. Approved.'
            ]
        );

        // Class Record - Approved
        Submission::firstOrCreate(
            ['faculty_id' => $faculty1->id, 'requirement_id' => $requirements[1]->id],
            [
                'file_path' => 'submissions/sample_class_record_cs.pdf',
                'status' => 'approved',
                'remarks' => 'Complete attendance records. Good job!'
            ]
        );

        // TOS Midterm - Pending
        Submission::firstOrCreate(
            ['faculty_id' => $faculty1->id, 'requirement_id' => $requirements[2]->id],
            [
                'file_path' => 'submissions/sample_tos_midterm_cs.pdf',
                'status' => 'pending',
                'remarks' => null
            ]
        );

        // Midterm Exam - Rejected
        Submission::firstOrCreate(
            ['faculty_id' => $faculty1->id, 'requirement_id' => $requirements[3]->id],
            [
                'file_path' => 'submissions/sample_midterm_exam_cs.pdf',
                'status' => 'rejected',
                'remarks' => 'Please include answer key and scoring rubric.'
            ]
        );

        // ========================================
        // Faculty 2 (IT Department) - 3 submissions
        // ========================================
        
        // Course Syllabus - Approved
        Submission::firstOrCreate(
            ['faculty_id' => $faculty2->id, 'requirement_id' => $requirements[0]->id],
            [
                'file_path' => 'submissions/sample_syllabus_it.pdf',
                'status' => 'approved',
                'remarks' => 'Comprehensive coverage of topics. Approved.'
            ]
        );

        // Class Record - Pending
        Submission::firstOrCreate(
            ['faculty_id' => $faculty2->id, 'requirement_id' => $requirements[1]->id],
            [
                'file_path' => 'submissions/sample_class_record_it.pdf',
                'status' => 'pending',
                'remarks' => null
            ]
        );

        // TOS Midterm - Pending
        Submission::firstOrCreate(
            ['faculty_id' => $faculty2->id, 'requirement_id' => $requirements[2]->id],
            [
                'file_path' => 'submissions/sample_tos_midterm_it.pdf',
                'status' => 'pending',
                'remarks' => null
            ]
        );

        // ========================================
        // Legacy Faculty - 2 submissions (all pending)
        // ========================================
        
        // Course Syllabus - Pending
        Submission::firstOrCreate(
            ['faculty_id' => $legacyFaculty->id, 'requirement_id' => $requirements[0]->id],
            [
                'file_path' => 'submissions/sample_syllabus_legacy.pdf',
                'status' => 'pending',
                'remarks' => null
            ]
        );

        // Grade Sheet - Pending
        if ($requirements->count() >= 7) {
            Submission::firstOrCreate(
                ['faculty_id' => $legacyFaculty->id, 'requirement_id' => $requirements[6]->id],
                [
                    'file_path' => 'submissions/sample_grade_sheet_legacy.pdf',
                    'status' => 'pending',
                    'remarks' => null
                ]
            );
        }

        $this->command->info('Sample submissions created successfully!');
        $this->command->info('Summary:');
        $this->command->info('  - Faculty 1 (CS): 4 submissions (2 approved, 1 pending, 1 rejected)');
        $this->command->info('  - Faculty 2 (IT): 3 submissions (1 approved, 2 pending)');
        $this->command->info('  - Legacy Faculty: 2 submissions (all pending)');
    }
}
