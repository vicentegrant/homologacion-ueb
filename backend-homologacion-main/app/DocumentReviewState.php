<?php

namespace App;

enum DocumentReviewState: string
{
    case Presentado = 'presentado';
    case Aprobado = 'aprobado';
    case Observado = 'observado';
}
