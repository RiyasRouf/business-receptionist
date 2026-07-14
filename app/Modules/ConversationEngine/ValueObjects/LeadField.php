<?php

namespace App\Modules\ConversationEngine\ValueObjects;

/**
 * L-01..L-18 (PRODUCT_REQUIREMENTS.md AB-02, F-06) — referenced by ID
 * everywhere in the docs but never named. Inferred from J1's "captures
 * name, child, grade, mobile" plus the admission-enquiry domain.
 * Flagged for founder sign-off — not the original (missing) artifact.
 *
 * L-01..L-13 required (F-06); L-14..L-18 optional.
 */
enum LeadField: string
{
    case ParentName = 'parent_name';                    // L-01
    case ParentPhone = 'parent_phone';                   // L-02
    case ParentEmail = 'parent_email';                   // L-03
    case ChildName = 'child_name';                       // L-04
    case ChildAge = 'child_age';                         // L-05
    case GradeApplyingFor = 'grade_applying_for';        // L-06
    case CurrentSchool = 'current_school';                // L-07
    case PreferredStartTerm = 'preferred_start_term';     // L-08
    case EnquiryType = 'enquiry_type';                    // L-09
    case PreferredContactMethod = 'preferred_contact_method'; // L-10
    case BestCallbackTime = 'best_callback_time';         // L-11
    case HowHeardAboutUs = 'how_heard_about_us';          // L-12
    case AdditionalNotes = 'additional_notes';            // L-13
    case Nationality = 'nationality';                     // L-14 optional
    case SiblingCurrentlyEnrolled = 'sibling_currently_enrolled'; // L-15 optional
    case AlternatePhone = 'alternate_phone';               // L-16 optional
    case MarketingConsent = 'marketing_consent';           // L-17 optional
    case LanguagePreference = 'language_preference';       // L-18 optional

    public function isRequired(): bool
    {
        return in_array($this, self::required(), true);
    }

    /** @return self[] */
    public static function required(): array
    {
        return [
            self::ParentName,
            self::ParentPhone,
            self::ParentEmail,
            self::ChildName,
            self::ChildAge,
            self::GradeApplyingFor,
            self::CurrentSchool,
            self::PreferredStartTerm,
            self::EnquiryType,
            self::PreferredContactMethod,
            self::BestCallbackTime,
            self::HowHeardAboutUs,
            self::AdditionalNotes,
        ];
    }

    /** L-01..L-06 — the MVP happy-path minimum (BAS-01 pass criteria). */
    public static function mvpMinimum(): array
    {
        return [
            self::ParentName,
            self::ParentPhone,
            self::ParentEmail,
            self::ChildName,
            self::ChildAge,
            self::GradeApplyingFor,
        ];
    }
}
