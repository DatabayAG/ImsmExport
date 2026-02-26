<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once(__DIR__ . '/class.ilImsmExportHelper.php');
require_once(__DIR__ . '/class.ilImsmExportConfig.php');
/**
 * Abstract parent class for all event hook plugin classes.
 * @author Michael Jansen <mjansen@databay.de>
 * @version $Id$
 * @ingroup ModulesTest
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
    public function getPluginName()
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
    public function getFormatLabel()
    {
        return $this->txt('imsm_format');
    }

    /**
     * @return string
     */
    protected function getFormatIdentifier()
    {
        return 'imsm';
    }

    /**
     * @param ilTestExportFilename $export_path
     * @throws ilException
     */
    protected function buildExportFile(ilTestExportFilename $export_path)
    {
        $config = $this->getConfig();
        $data = $this->getTest()->getCompleteEvaluationData(true);
        $titles = $this->getTest()->getQuestionTitlesAndIndexes();

        $positions = array();
        $pos = 0;
        $row = 0;

        // titles are indexed by question id, but sorted by test sequence
        // columns are added in the order of the test sequence
        foreach ($titles as $aid => $title) {
            $question = assQuestion::_instantiateQuestion($aid);

            if ($this->isQuestionTypeValid($question->getQuestionType())) {
                $positions[$aid] = $pos;
                $pos++;
            }
        }

        // fill csv header
        $a_csv_header_row = array();
        $col = 0;
        $a_csv_header_row[$col++] = $this->addEnclosure('last_name');
        $a_csv_header_row[$col++] = $this->addEnclosure('first_name');
        $a_csv_header_row[$col++] = $this->addEnclosure('Matrikel');
        $a_csv_header_row[$col++] = $this->addEnclosure('user');

        foreach ($titles as $aid => $title) {
            $question = assQuestion::_instantiateQuestion($aid);

            if ($this->isQuestionTypeValid($question->getQuestionType())) {
                $imsm_id = $question->getExternalId();
                $a_csv_header_row[$col + $positions[$aid]] = $this->addEnclosure($imsm_id);
            }
        }
        $a_csv_header_row[count($a_csv_header_row)] = $this->addEnclosure('time');

        // fill csv body
        $a_csv_data_rows = array();
        $a_csv_data_rows[$row++] = $a_csv_header_row;

        foreach ($data->getParticipants() as $active_id => $userdata) {
            $user = new ilObjUser($userdata->getUserID());

            $a_csv_row = array();
            $col = 0;
            $anon_id = $row;
            $a_csv_row[$col++] = $this->addEnclosure($config->getUseFullname() ? $user->getLastname() : "lastname_" . $anon_id);
            $a_csv_row[$col++] = $this->addEnclosure($config->getUseFullname() ? $user->getFirstname() : "firstname_" . $anon_id);
            $a_csv_row[$col++] = $this->addEnclosure($config->getUseMatriculation() ? $user->getMatriculation() : "1111" . $anon_id);
            $a_csv_row[$col++] = $this->addEnclosure($config->getUseLogin() ? $user->getLogin() : "id_" . $anon_id);

            $pass = $userdata->getScoredPass();
            if (is_object($userdata) && is_array($userdata->getQuestions($pass))) {
                foreach ($userdata->getQuestions($pass) as $question) {
                    $objQuestion = assQuestion::_instantiateQuestion($question["id"]);
                    $type = $objQuestion->getQuestionType();
                    if (is_object($objQuestion) && $this->isQuestionTypeValid($objQuestion->getQuestionType())) {
                        $solutions = $objQuestion->getSolutionValues($active_id, $pass);
                        $answers = [];
                        if (in_array($type, [self::SINGLE_CHOICE, self::MULTIPLE_CHOICE])) {
                            $answers = $this->getAnswersForSingleAndMultipleChoiceQuestions($solutions);
                        } elseif ($type === self::K_PRIM) {
                            $answers = $this->getAnswersForKPrimChoiceQuestions($solutions);
                        } elseif ($type === self::LONG_MENU) {
                            $answers = $this->getAnswersForLongMenuQuestions($solutions, count($objQuestion->getAnswers()));
                        } elseif ($type === self::NUMERIC) {
                            $answers = $this->getAnswersForNumericQuestions($solutions);
                        } elseif ($type === self::TEXT_QUESTION) {
                            $answers = $this->getAnswersForTextQuestions($solutions, $active_id, $pass, $question["id"]);
                        }
                        $pos = $positions[$question["id"]];
                        $a_csv_row[$col + $pos] = '"' . implode(",", $answers) . '"';
                    }
                }
            }

            $lv = getdate($data->getParticipant($active_id)->getLastVisit());
            $tstamp = mktime($lv['hours'], $lv['minutes'], $lv['seconds'], $lv['mon'], $lv['mday'], $lv['year']);
            $lastvisit = date("d.m.Y G:i:s", $tstamp);
            $a_csv_row[count($a_csv_row)] = $lastvisit;
            ksort($a_csv_row);
            $a_csv_data_rows[$row] = $a_csv_row;
            $row++;
        }

        $csv = "";
        $separator = ";";
        foreach ($a_csv_data_rows as $evalrow) {
            $csvrow = $this->processCSVRow($evalrow);
            $csv .= join($separator, $csvrow) . "\n";
        }

        // use ilFileUtils::getASCIIFilename from ILIAS 8 on
        $additional = ilUtil::getASCIIFilename($this->getTest()->getImportId());
        if (empty($additional)) {
            $additional = 'csv';
        }

        ilUtil::makeDirParents(dirname($export_path->getPathname('csv', $additional)));
        
        // UTF-8 BOM hinzufügen für Excel-Kompatibilität
        $bom = "\xEF\xBB\xBF";
        file_put_contents($export_path->getPathname('csv', $additional), $bom . $csv);
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

    protected function addEnclosure(string $string) : string
    {
        return '"' . $string . '"';
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

    public function &processCSVRow($row) : array
    {
        $result = array();
        foreach ($row as $rowindex => $entry) {
            $entry = str_replace(chr(13) . chr(10), chr(10), $entry);

            $result[$rowindex] = $entry;
        }
        return $result;
    }
}
