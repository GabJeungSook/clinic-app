<?php

namespace App\Support\Chart;

use App\Enums\FaceShape;
use App\Enums\SkinType;

/**
 * Canonical option lists for the patient chart's fixed checklists, transcribed
 * from the clinic's paper "Waiver Form & Patient Chart". One source of truth:
 * the same maps drive both validation (allowed keys) and the Vue checkboxes
 * (key + human label). Free-text "Others:" for each group is stored separately.
 *
 * Each constant is [key => label]. Use keys() for Rule::in and options() to
 * hand the label list to the front end.
 */
class ChartOptions
{
    /** "I have" — patient history. */
    public const HAVE = [
        'allergies' => 'Allergies',
        'asthma' => 'Asthma',
        'diabetes_mellitus' => 'Diabetes Mellitus',
        'hypertension' => 'Hypertension',
        'aids' => 'AIDS',
        'hepatitis' => 'Hepatitis',
    ];

    /** "I am taking" — current medications. */
    public const TAKING = [
        'maintenance_medication' => 'Maintenance Medication',
        'antibiotics' => 'Antibiotics',
        'blood_thinners' => 'Blood Thinners',
    ];

    /** "Current condition". */
    public const CONDITION = [
        'normal' => 'Normal',
        'pregnant' => 'Pregnant',
        'had_skin_procedures' => 'Had Skin Procedures',
        'breastfeeding' => 'Breastfeeding',
    ];

    /** Aesthetic procedures done (each also records "if yes, when"). */
    public const PROCEDURES = [
        'botox' => 'Botox',
        'fillers' => 'Fillers',
        'collagen' => 'Collagen',
        'threads' => 'Threads',
    ];

    /** Initial plan checklist. */
    public const INITIAL_PLAN = [
        'facial' => 'Facial',
        'warts_removal' => 'Warts Removal',
        'facial_peel' => 'Facial Peel',
        'pimple_injection' => 'Pimple Injection',
        'micro_needling' => 'Micro Needling',
        'co2_laser' => 'CO2 Laser',
        'skin_regiment' => 'Skin Regiment',
    ];

    /** Doctor's assessment — observed skin conditions. */
    public const ASSESSMENT = [
        'acne' => 'Acne',
        'acne_marks' => 'Acne Marks',
        'pigmentation' => 'Pigmentation',
        'atopic_dermatitis' => 'Atopic Dermatitis',
        'seborrheic_dermatitis' => 'Seborrheic Dermatitis',
        'comedones' => 'Comedones',
        'micro_comedones' => 'Micro-Comedones',
        'acne_scars' => 'Acne Scars',
        'warts' => 'Warts',
        'syringoma' => 'Syringoma',
        'millia' => 'Millia',
        'open_pores' => 'Open Pores',
        'rosacea' => 'Rosacea',
        'vitiligo' => 'Vitiligo',
        'hypopigmentation' => 'Hypopigmentation',
        'skin_irritation' => 'Skin Irritation',
    ];

    /** @return array<int, string> Allowed keys for a given [key => label] map. */
    public static function keys(array $map): array
    {
        return array_keys($map);
    }

    /**
     * The full set of option lists handed to the front end, each as an array of
     * { value, label } plus the two single-select enums.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public static function forFrontend(): array
    {
        return [
            'have' => self::options(self::HAVE),
            'taking' => self::options(self::TAKING),
            'condition' => self::options(self::CONDITION),
            'procedures' => self::options(self::PROCEDURES),
            'initialPlan' => self::options(self::INITIAL_PLAN),
            'assessment' => self::options(self::ASSESSMENT),
            'skinTypes' => array_map(
                fn (SkinType $s) => ['value' => $s->value, 'label' => $s->label()],
                SkinType::cases(),
            ),
            'faceShapes' => array_map(
                fn (FaceShape $f) => ['value' => $f->value, 'label' => $f->label()],
                FaceShape::cases(),
            ),
        ];
    }

    /**
     * @param  array<string, string>  $map
     * @return array<int, array{value: string, label: string}>
     */
    private static function options(array $map): array
    {
        return array_map(
            fn (string $key, string $label) => ['value' => $key, 'label' => $label],
            array_keys($map),
            array_values($map),
        );
    }
}
