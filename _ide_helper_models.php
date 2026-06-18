<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id_academic_period
 * @property string $name Contoh: Semester Ganjil 2024/2025
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property bool $is_active Hanya 1 periode boleh aktif
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $classes
 * @property-read int|null $classes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod inRange($date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod whereIdAcademicPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPeriod whereUpdatedAt($value)
 */
	class AcademicPeriod extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_announcement
 * @property int|null $id_class
 * @property string|null $title
 * @property string $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Classes|null $class
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereIdAnnouncement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereIdClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement whereUpdatedAt($value)
 */
	class Announcement extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_qr
 * @property int $id_schedule
 * @property string|null $session_date
 * @property string $key
 * @property string $time_start
 * @property string|null $time_end
 * @property string $name_agenda
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Schedule $schedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereIdQr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereIdSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereNameAgenda($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereSessionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereTimeEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereTimeStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttendanceSession whereUpdatedAt($value)
 */
	class AttendanceSession extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_conversation
 * @property string $type
 * @property int|null $id_class
 * @property int $id_initiator
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Classes|null $academicClass
 * @property-read \App\Models\Classes|null $class
 * @property-read \App\Models\User_si $initiator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatMessage> $messages
 * @property-read int|null $messages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User_si> $participants
 * @property-read int|null $participants_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereIdClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereIdConversation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereIdInitiator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation whereUpdatedAt($value)
 */
	class ChatConversation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_message
 * @property int $id_conversation
 * @property int $id_user_si
 * @property string $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ChatConversation $conversation
 * @property-read \App\Models\User_si $sender
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereIdConversation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereIdMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereIdUserSi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatMessage whereUpdatedAt($value)
 */
	class ChatMessage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_class
 * @property int $id_subject
 * @property int $id_academic_period
 * @property string $code_class
 * @property int $member_class
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicPeriod $academicPeriod
 * @property-read \App\Models\ChatConversation|null $conversation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User_si> $lecturers
 * @property-read int|null $lecturers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Schedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User_si> $students
 * @property-read int|null $students_count
 * @property-read \App\Models\Subject $subject
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereCodeClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereIdAcademicPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereIdClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereIdSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereMemberClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Classes whereUpdatedAt($value)
 */
	class Classes extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_grades
 * @property int $min_grade Nilai minimal (0 - 100)
 * @property int $max_grade Nilai maksimal (0 - 100)
 * @property string $letter Nilai huruf: A, A-, B+, dst.
 * @property numeric $ip_skor Indeks prestasi (0.00 - 4.00)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion whereIdGrades($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion whereIpSkor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion whereLetter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion whereMaxGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion whereMinGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GradeConversion whereUpdatedAt($value)
 */
	class GradeConversion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_grades
 * @property int $id_user_si
 * @property int $id_subject
 * @property int|null $id_class
 * @property string|null $grade
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Classes|null $class
 * @property-read \App\Models\Subject $subject
 * @property-read \App\Models\User_si $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades whereGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades whereIdClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades whereIdGrades($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades whereIdSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades whereIdUserSi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Grades whereUpdatedAt($value)
 */
	class Grades extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_notification
 * @property int $id_user_si
 * @property int|null $id_conversation
 * @property int|null $id_message
 * @property int|null $id_announcement
 * @property \Illuminate\Support\Carbon $sent_at
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Announcement|null $announcement
 * @property-read \App\Models\ChatConversation|null $conversation
 * @property-read \App\Models\ChatMessage|null $message
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification read()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification unread()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIdAnnouncement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIdConversation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIdMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIdNotification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereIdUserSi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereSendAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Notification whereUpdatedAt($value)
 */
	class Notification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_presence
 * @property int $id_schedule
 * @property int $id_student
 * @property string|null $time
 * @property string $qr_session
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AttendanceSession|null $attendanceSession
 * @property-read \App\Models\User_si $student
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence whereIdPresence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence whereIdSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence whereIdStudent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence whereQrSession($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence whereTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presence whereUpdatedAt($value)
 */
	class Presence extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_program
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User_si> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Programs newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Programs newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Programs query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Programs whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Programs whereIdProgram($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Programs whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Programs whereUpdatedAt($value)
 */
	class Programs extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_schedule
 * @property int $id_class
 * @property string $date
 * @property int $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Classes $academicClass
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereIdClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereIdSchedule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereUpdatedAt($value)
 */
	class Schedule extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_staff_profile
 * @property int $id_user_si
 * @property string $full_name
 * @property string $employee_id_number
 * @property string|null $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User_si $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereEmployeeIdNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereIdStaffProfile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereIdUserSi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereUpdatedAt($value)
 */
	class StaffProfile extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_profile
 * @property int $id_user_si
 * @property string $registration_number
 * @property string $registration_status
 * @property string $full_name
 * @property string|null $gender
 * @property string|null $religion
 * @property string|null $birth_place
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property string|null $nik
 * @property string|null $birth_certificate_number
 * @property string|null $no_kk
 * @property string|null $citizenship
 * @property int|null $birth_order
 * @property int|null $number_of_siblings
 * @property string|null $full_address
 * @property string|null $dusun
 * @property string|null $kelurahan
 * @property string|null $kecamatan
 * @property string|null $city_regency
 * @property string|null $province
 * @property string|null $postal_code
 * @property string|null $previous_school
 * @property string|null $graduation_status
 * @property string|null $last_ijazah
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $classes
 * @property-read int|null $classes_count
 * @property-read \App\Models\User_si $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereBirthCertificateNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereBirthOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereBirthPlace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCitizenship($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCityRegency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereDusun($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereFullAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereGraduationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereIdProfile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereIdUserSi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereKecamatan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereKelurahan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereLastIjazah($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereNik($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereNoKk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereNumberOfSiblings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile wherePreviousSchool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereRegistrationNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereRegistrationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereReligion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereUpdatedAt($value)
 */
	class StudentProfile extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_subject
 * @property string $name_subject
 * @property string $code_subject
 * @property int $sks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $classes
 * @property-read int|null $classes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Grades> $grades
 * @property-read int|null $grades_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCodeSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereIdSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereNameSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereSks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subject whereUpdatedAt($value)
 */
	class Subject extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id_user_si
 * @property string $name
 * @property string $username
 * @property string $email
 * @property string|null $profile_image
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property int|null $id_program
 * @property string $role
 * @property bool $is_active
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatConversation> $chatConversations
 * @property-read int|null $chat_conversations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $classes
 * @property-read int|null $classes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Grades> $grades
 * @property-read int|null $grades_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \App\Models\StudentProfile|null $profile
 * @property-read \App\Models\Programs|null $program
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\StaffProfile|null $staffProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $studentClasses
 * @property-read int|null $student_classes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Classes> $teachingClasses
 * @property-read int|null $teaching_classes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereIdProgram($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereIdUserSi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereProfileImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User_si withoutRole($roles, $guard = null)
 */
	class User_si extends \Eloquent {}
}

