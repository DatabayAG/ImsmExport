<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\Filesystem\Filesystem;
use ILIAS\Filesystem\Util\LegacyPathHelper;
use ILIAS\Test\ExportImport\ExportFilename;

/**
 * @author Michael Jansen <mjansen@databay.de>
 */
class ilImsmExportPlugin extends ilTestExportPlugin
{
    const SINGLE_CHOICE = 'assSingleChoice';
    const MULTIPLE_CHOICE = 'assMultipleChoice';
    const K_PRIM = 'assKprimChoice';
    const LONG_MENU = 'assLongMenu';
    const NUMERIC = 'assNumeric';
    const TEXT_QUESTION = 'assTextQuestion';

    /**
     * Get Plugin Name. Must be same as in class name il<Name>Plugin
     * and must correspond to plugins subdirectory name.
     * Must be overwritten in plugin class of plugin
     * (and should be made final)
     * @return string Plugin Name
     */
    public function getPluginName() : string
    {
        return 'ImsmExport';
    }

    public function getConfig() : ilImsmExportConfig
    {
        return new ilImsmExportConfig(new ilSetting('plugin.texpimsm'));
    }

    /**
     * @return string
     */
    public function getFormatLabel() : string
    {
        return $this->txt('imsm_format');
    }

    /**
     * @return string
     */
    protected function getFormatIdentifier() : string
    {
        return 'imsm';
    }

    /**
     * @throws ilException
     */
    protected function buildExportFile(ExportFilename $export_path): void
    {
        $config = $this->getConfig();
        $data = $this->getTest()->getCompleteEvaluationData();
        $titles = $this->getTest()->getQuestionTitlesAndIndexes();

        $positions = [];
        $pos = 0;
        $row = 0;

        // titles are indexed by question id, but sorted by test sequence
        // columns are added in the order of the test sequence
        foreach ($titles as $aid => $title) {
            $question = assQuestion::instantiateQuestion($aid);

            if ($this->isQuestionTypeValid($question->getQuestionType())) {
                $positions[$aid] = $pos;
                $pos++;
            }
        }

        // fill csv header
        $header_row = [];
        $col = 0;
        $header_row[$col++] = 'last_name';
        $header_row[$col++] = 'first_name';
        $header_row[$col++] = 'Matrikel';
        $header_row[$col++] = 'user';

        foreach ($titles as $aid => $title) {
            $question = assQuestion::instantiateQuestion($aid);

            if ($this->isQuestionTypeValid($question->getQuestionType())) {
                $imsm_id = $question->getExternalId();
                $header_row[$col + $positions[$aid]] = $imsm_id;
            }
        }
        $header_row[count($header_row)] = 'time';

        // fill csv body
        $all_rows = [];
        $all_rows[$row++] = $header_row;

        foreach ($data->getParticipants() as $active_id => $userdata) {
            $user = new ilObjUser($userdata->getUserID());

            $data_row = [];
            $col = 0;
            $anon_id = $row;
            $data_row[$col++] = $config->getUseFullname() ? $user->getLastname() : "lastname_" . $anon_id;
            $data_row[$col++] = $config->getUseFullname() ? $user->getFirstname() : "firstname_" . $anon_id;
            $data_row[$col++] = $config->getUseMatriculation() ? $user->getMatriculation() : "1111" . $anon_id;
            $data_row[$col++] = $config->getUseLogin() ? $user->getLogin() : "id_" . $anon_id;

            $pass = $userdata->getScoredPass();
            if (is_object($userdata) && is_array($userdata->getQuestions($pass))) {
                foreach ($userdata->getQuestions($pass) as $question) {
                    $objQuestion = assQuestion::instantiateQuestion($question["id"]);
                    $type = $objQuestion->getQuestionType();
                    if (is_object($objQuestion) && $this->isQuestionTypeValid($objQuestion->getQuestionType())) {
                        $solutions = $objQuestion->getSolutionValues($active_id, $pass);
                        $answers = [];
                        if (in_array($type, [self::SINGLE_CHOICE, self::MULTIPLE_CHOICE])) {
                            $answers = $this->getAnswersForSingleAndMultipleChoiceQuestions($solutions);
                        } elseif ($type === self::K_PRIM) {
                            $answers = $this->getAnswersForKPrimChoiceQuestions($solutions);
                        } elseif ($type === self::LONG_MENU) {
                            $answers = $this->getAnswersForLongMenuQuestions($solutions,
                                count($objQuestion->getAnswers()));
                        } elseif ($type === self::NUMERIC) {
                            $answers = $this->getAnswersForNumericQuestions($solutions);
                        } elseif ($type === self::TEXT_QUESTION) {
                            $answers = $this->getAnswersForTextQuestions($solutions, $active_id, $pass, $question["id"]);
                        }
                        $pos = $positions[$question["id"]];
                        $data_row[$col + $pos] = implode(",", $answers);
                    }
                }
            }

            $lv = getdate($data->getParticipant($active_id)->getLastVisit()?->getTimestamp());
            $tstamp = mktime($lv['hours'], $lv['minutes'], $lv['seconds'], $lv['mon'], $lv['mday'], $lv['year']);
            $lastvisit = date("d.m.Y G:i:s", $tstamp);
            $data_row[count($data_row)] = $lastvisit;
            ksort($data_row);
            $all_rows[$row] = $data_row;
            $row++;
        }

        $writer = new ilCSVWriter();
        $writer->setDelimiter('"');
        $writer->setSeparator(';');

        foreach ($all_rows as $row) {
            foreach ($row as $column) {
                $writer->addColumn($column);
            }
            $writer->addRow();
        }

        $additional = ilFileUtils::getASCIIFilename($this->getTest()->getImportId());
        if (empty($additional)) {
            $additional = 'csv';
        }

        $absolute_path = $export_path->getPathname('csv', $additional);
        $relative_path = LegacyPathHelper::createRelativePath($absolute_path);
        $filesystem = LegacyPathHelper::deriveFilesystemFrom($absolute_path);
        if (!$filesystem->hasDir(dirname($relative_path))) {
            $filesystem->createDir(dirname($relative_path));
        }


        // Add UTF-8 BOM for MS Excel compatibility
        $bom = "\xEF\xBB\xBF";
        $filesystem->write($relative_path, $bom . $writer->getCSVString());
    }

    protected function isQuestionTypeValid(string $type) : bool
    {
        $valid_types = [
            self::SINGLE_CHOICE,
            self::MULTIPLE_CHOICE,
            self::K_PRIM,
            self::LONG_MENU,
            self::NUMERIC,
            self::TEXT_QUESTION
        ];

        if (in_array($type, $valid_types)) {
            return true;
        }
        return false;
    }

    protected function getAnswersForSingleAndMultipleChoiceQuestions(array $solutions) : array
    {
        $answers = [];
        for ($i = 0; $i < count($solutions); $i++) {
            $selected_answer = chr(65 + $solutions[$i]["value1"]);
            array_push($answers, $selected_answer);
        }
        sort($answers);
        return $answers;
    }

    protected function getAnswersForKPrimChoiceQuestions(array $solutions) : array
    {
        $answers = [];
        if (sizeof($solutions) === 0) {
            return $answers;
        }
        $must_exists = ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'];
        for ($i = 0; $i < count($solutions); $i++) {
            $selected_answer = chr(65 + $solutions[$i]["value1"]);
            unset($must_exists[$selected_answer]);
            if ($solutions[$i]["value2"] === "1") {
                $selected_answer .= '+';
            } else {
                $selected_answer .= '-';
            }
            array_push($answers, $selected_answer);
        }
        if (count($answers) < 4) {
            $answers = array_merge($answers, $must_exists);
        }
        sort($answers);
        return $answers;
    }

    protected function getAnswersForLongMenuQuestions(array $solutions, $answer_count) : array
    {
        $answers = [];
        $empty_count = 0;
        for ($i = 0; $i < $answer_count; $i++) {
            if (!isset($answers[$i])) {
                $answers[$i] = '';
            }

            if (isset($solutions[$i])) {
                $pos = (int) $solutions[$i]["value1"];
                // Sanitize user input before adding to CSV
                $sanitizedValue = ilImsmExportHelper::sanitizeFreetextForCsv($solutions[$i]["value2"]);
                // Kein addEnclosure hier - wird in buildExportFile gemacht
                $answers[$pos] = $sanitizedValue;
            } else {
                $empty_count++;
            }
        }

        if ($empty_count === $answer_count) {
            $answers = [];
        }

        return $answers;
    }

    protected function getAnswersForNumericQuestions(array $solutions) : array
    {
        $answers = [];

        for ($i = 0; $i < count($solutions); $i++) {
            $answers[$i] = ilImsmExportHelper::sanitizeFreetextForCsv($solutions[$i]["value1"]);
        }

        return $answers;
    }

    protected function getAnswersForTextQuestions(array $solutions, $active_id, $pass, $question_id) : array
    {
        $answers = [];

        for ($i = 0; $i < count($solutions); $i++) {
            if (isset($solutions[$i]["value1"]) && $solutions[$i]["value1"] !== '') {
                // Freitext-Eingabe sanitizen
                $sanitizedText = ilImsmExportHelper::sanitizeFreetextForCsv($solutions[$i]["value1"]);
                $answers[] = $sanitizedText;
            }
        }

        // Falls keine Antwort gegeben wurde
        if (empty($answers)) {
            $answers = [''];
        }

        // Prüfen ob manuelle Bewertung stattgefunden hat
        global $DIC;
        $db = $DIC->database();

        $query = "SELECT points, manual 
                  FROM tst_test_result 
                  WHERE active_fi = " . $db->quote($active_id, 'integer') . "
                  AND question_fi = " . $db->quote($question_id, 'integer') . "
                  AND pass = " . $db->quote($pass, 'integer');

        $result = $db->query($query);

        if ($row = $db->fetchAssoc($result)) {
            if ($row['manual'] == 1) {
                // Manuelle Bewertung erfolgt
                // Single quotes (') statt double quotes (") verwenden, um CSV-Escaping-Probleme zu vermeiden,
                // da " bereits als CSV-Steuerzeichen verwendet wird
                $textPart = implode(' ', $answers);
                $answers = ["'" . $textPart . "'", $row['points']];
            }
        }

        return $answers;
    }
}
