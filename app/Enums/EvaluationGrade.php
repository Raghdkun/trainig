<?php

namespace App\Enums;

/**
 * The manager's overall letter grade for an employee entering the
 * Development Zone — A is the strongest, D the weakest.
 */
enum EvaluationGrade: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
}
