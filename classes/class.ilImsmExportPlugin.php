<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/Test/classes/class.ilTestExportPlugin.php';
require_once 'Modules/TestQuestionPool/classes/class.assQuestion.php';

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

    /**
     * Get Plugin Name. Must be same as in class name il<Name>Plugin
     * and must correspond to plugins subdirectory name.
     * Must be overwritten in plugin class of plugin
     * (and should be made final)
     * @return string Plugin Name
     */
    function getPluginName()
    {
        return 'ImsmExport';
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
     * @param ilTestExportFilename $filename
     * @throws ilException
     */
    protected function buildExportFile(ilTestExportFilename $filename)
    {
        $data = $this->getTest()->getCompleteEvaluationData(TRUE);
        $titles = $this->getTest()->getQuestionTitlesAndIndexes();
        $orderedIds = $this->getTest()->getQuestions();
        asort($orderedIds);

        $positions = array();
        $pos = 0;
        $row = 0;
        foreach ($orderedIds as $oid) {
            $positions[$oid] = $pos;
            $pos++;
        }

        // fill csv header
        $a_csv_header_row = array();
        $col = 0;
        $a_csv_header_row[$col++] = 'last_name';
        $a_csv_header_row[$col++] = 'first_name';
        $a_csv_header_row[$col++] = 'Matrikel';
        $a_csv_header_row[$col++] = 'user';

        foreach ($titles as $aid => $title) {
            $question = assQuestion::_instantiateQuestion($aid);

            if ($this->isQuestionTypeValid($question->getQuestionType())) {
                $imsm_id = $question->getExternalId();
                $a_csv_header_row[$col + $positions[$aid]] = $imsm_id;
            }
        }
        $a_csv_header_row[count($a_csv_header_row)] = 'time';

        // fill csv body
        $a_csv_data_rows = array();
        $a_csv_data_rows[$row++] = $a_csv_header_row;

        foreach ($data->getParticipants() as $active_id => $userdata) {
            $a_csv_row = array();
            $col = 0;
            $anon_id = $row;
            $a_csv_row[$col++] = "lastname_" . $anon_id;
            $a_csv_row[$col++] = "firstname_" . $anon_id;
            $a_csv_row[$col++] = "1111" . $anon_id;
            $a_csv_row[$col++] = "id_" . $anon_id;

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
                        }
                        $pos = $positions[$question["id"]];
                        $a_csv_row[$col + $pos] = implode(",", $answers);
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
            $csvrow = $this->getTest()->processCSVRow($evalrow, FALSE, $separator);
            $csv .= join($separator, $csvrow) . "\n";
        }

        ilUtil::makeDirParents(dirname($filename->getPathname('csv', 'csv')));
        file_put_contents($filename->getPathname('csv', 'csv'), $csv);
    }

    protected function isQuestionTypeValid(string $type): bool
    {
        $valid_types = [self::SINGLE_CHOICE, self::MULTIPLE_CHOICE, self::K_PRIM, self::LONG_MENU, self::NUMERIC];

        if (in_array($type, $valid_types)) {
            return true;
        }
        return false;
    }

    protected function getAnswersForSingleAndMultipleChoiceQuestions(array $solutions): array
    {
        $answers = [];
        for ($i = 0; $i < count($solutions); $i++) {
            $selected_answer = chr(65 + $solutions[$i]["value1"]);
            array_push($answers, $selected_answer);
        }
        sort($answers);
        return $answers;
    }

    protected function getAnswersForKPrimChoiceQuestions(array $solutions): array
    {
        $answers = [];
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

    protected function getAnswersForNumericQuestions(array $solutions): array
    {
        $answers = [];

        for ($i = 0; $i < count($solutions); $i++) {
            $answers[$i] = $solutions[$i]["value1"];
        }

        return $answers;
    }

    protected function getAnswersForLongMenuQuestions(array $solutions, $answer_count): array
    {
        $answers = [];

        for ($i = 0; $i < $answer_count; $i++) {
            if( ! isset($answers[$i])){
                $answers[$i] = '';
            }

            if(isset($solutions[$i])) {
                $pos = (int) $solutions[$i]["value1"];
                $answers[$pos] = $solutions[$i]["value2"];
                }
            }

        return $answers;
    }

}