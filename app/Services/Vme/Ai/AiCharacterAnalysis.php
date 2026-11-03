<?php

namespace App\Services\Vme\Ai;

/**
 * The contract every AI character implements. An analysis only READS data (the VME snapshot
 * context and FreeDOMS tables) and returns a result; it never changes an operational record.
 * Persisting the result is the AiCharacterService's job, into ai_character_runs only.
 *
 * To add a character: implement this, then add one entry to config('vme.ai.characters').
 * The renderer needs no change.
 */
interface AiCharacterAnalysis
{
    public function analyze(AiContext $context): AiAnalysisResult;
}
