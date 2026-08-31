<?php

namespace App\Directory;

/**
 * Resolves reader presentation from directory-owned attributes only.
 *
 * This classification never grants an application role. In an ambiguous
 * teacher/student case the lower-privilege student presentation wins; when AD
 * supplies no recognized audience marker, employee is the conservative
 * non-student fallback for a corporate directory identity.
 */
final class ActiveDirectoryReaderCategoryResolver
{
    /** @var list<string> */
    private const STUDENT_MARKERS = [
        'student', 'students', 'undergraduate', 'postgraduate', 'doctoral student', 'phd student',
        'студент', 'студенты', 'обучающийся', 'обучающиеся', 'магистрант', 'докторант',
        'студенттер', 'білім алушы', 'магистранттар', 'докторанттар',
    ];

    /** @var list<string> */
    private const TEACHER_MARKERS = [
        'teacher', 'teaching staff', 'academic staff', 'faculty staff', 'lecturer', 'professor',
        'associate professor', 'assistant professor', 'instructor',
        'преподаватель', 'профессор', 'доцент', 'педагог', 'учитель',
        'оқытушы', 'профессор', 'доцент', 'педагог',
    ];

    /** @var list<string> */
    private const EMPLOYEE_MARKERS = [
        'employee', 'employees', 'staff', 'personnel', 'administration', 'administrative',
        'сотрудник', 'сотрудники', 'работник', 'персонал', 'администрация',
        'қызметкер', 'қызметкерлер', 'персонал', 'әкімшілік',
    ];

    public function resolve(ActiveDirectoryUser $identity): string
    {
        $title = $this->normalize((string) $identity->title);
        $department = $this->normalize((string) $identity->department);
        $groups = array_map(
            fn (string $group): string => $this->normalize($group),
            array_values(array_filter($identity->groups, 'is_string')),
        );

        $student = $this->containsAny($title, self::STUDENT_MARKERS)
            || $this->containsAny($department, self::STUDENT_MARKERS)
            || $this->anyContains($groups, self::STUDENT_MARKERS);
        $teacher = $this->containsAny($title, self::TEACHER_MARKERS)
            || $this->containsAny($department, ['teaching staff', 'academic staff', 'faculty staff', 'кафедра преподавателей', 'оқытушылар'])
            || $this->anyContains($groups, [...self::TEACHER_MARKERS, 'cn=faculty,', 'cn=faculty']);

        if ($student) {
            return 'student';
        }

        if ($teacher) {
            return 'teacher';
        }

        if ($this->containsAny($title, self::EMPLOYEE_MARKERS)
            || $this->containsAny($department, self::EMPLOYEE_MARKERS)
            || $this->anyContains($groups, self::EMPLOYEE_MARKERS)
            || $title !== ''
            || $department !== '') {
            return 'employee';
        }

        return 'employee';
    }

    /** @param list<string> $values @param list<string> $markers */
    private function anyContains(array $values, array $markers): bool
    {
        foreach ($values as $value) {
            if ($this->containsAny($value, $markers)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $markers */
    private function containsAny(string $value, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (str_contains($value, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
