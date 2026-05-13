<?php

namespace App\Models;

use App\Jobs\BookMoodleGroupExam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_hemis_id',
        'specialty_hemis_id',
        'curriculum_hemis_id',
        'semester_code',
        'group_hemis_id',
        'student_hemis_id',
        'subject_id',
        'subject_name',
        'oski_date',
        'oski_na',
        'oski_time',
        'oski_resit_date',
        'oski_resit_time',
        'oski_resit2_date',
        'oski_resit2_time',
        'test_date',
        'test_na',
        'test_time',
        'test_resit_date',
        'test_resit_time',
        'test_resit2_date',
        'test_resit2_time',
        'education_year',
        'created_by',
        'updated_by',
        'oski_moodle_synced_at',
        'oski_moodle_response',
        'oski_moodle_error',
        'test_moodle_synced_at',
        'test_moodle_response',
        'test_moodle_error',
        'test_assignment_mode',
        'oski_assignment_mode',
    ];

    protected $casts = [
        'oski_date' => 'date',
        'oski_na' => 'boolean',
        'test_date' => 'date',
        'test_na' => 'boolean',
        'oski_resit_date' => 'date',
        'oski_resit2_date' => 'date',
        'test_resit_date' => 'date',
        'test_resit2_date' => 'date',
        'oski_moodle_synced_at' => 'datetime',
        'test_moodle_synced_at' => 'datetime',
        'oski_moodle_response' => 'array',
        'test_moodle_response' => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_hemis_id', 'specialty_hemis_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_hemis_id', 'group_hemis_id');
    }

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_hemis_id', 'curricula_hemis_id');
    }


    /**
     * Re-push the booking to Moodle whenever the attempt-1 schedule
     * window changes.
     *
     * The proctor / academic UI has multiple endpoints that mutate the
     * oski_* / test_* date and time columns (saveTestTime, autoTimeAll,
     * AutoAssignService::distribute, manual edits via Tinker, etc.).
     * Without a centralised hook, each call site has to remember to
     * dispatch BookMoodleGroupExam — and some of them already don't,
     * which left the Moodle bookings stuck at whatever Mark pushed on
     * the first save.
     *
     * Listening on saved() gives us a single place that watches the
     * window columns and triggers a re-push on every legitimate
     * change. The job is idempotent on the Moodle side (book() updates
     * the existing local_hemisexport_cutoffs / quiz_overrides rows in
     * place) so a duplicate dispatch from an explicit call site does
     * no harm.
     *
     * Only attempt-1 columns are watched. Resit columns
     * (oski_resit_*, test_resit_*, etc.) belong to attempt 2/3 and are
     * not supported by the current book_group_exam web service, so we
     * deliberately leave them alone.
     */
    protected static function booted(): void
    {
        static::saved(function (self $schedule) {
            foreach (['oski', 'test'] as $yn) {
                $watched = [$yn . '_date', $yn . '_time', $yn . '_na'];
                if (!$schedule->wasChanged($watched)) {
                    continue;
                }
                // Only push when the full window is set and not flagged N/A.
                if (
                    empty($schedule->{$yn . '_date'}) ||
                    empty($schedule->{$yn . '_time'}) ||
                    !empty($schedule->{$yn . '_na'})
                ) {
                    continue;
                }
                BookMoodleGroupExam::dispatch($schedule->id, $yn);
            }
        });
    }
}
