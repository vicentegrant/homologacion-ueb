<?php

namespace App;

enum AnalysisConclusion: string
{
    case Total = 'total';
    case Parcial = 'parcial';
    case Rechazada = 'rechazada';
}
