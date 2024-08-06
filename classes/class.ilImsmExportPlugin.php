<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\Filesystem\Filesystem;
use ILIAS\Filesystem\Util\LegacyPathHelper;

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
     * @param ilTestExportFilename $export_path
     * @throws ilException
     */
    protected function buildExportFile(ilTestExportFilename $export_path)
    {
        $config = $this->getConfig();
        $data = $this->getTest()->getCompleteEvaluationData(true);
        $titles = $this->getTest()->getQuestionTitlesAndIndexes();
        $orderedIds = $this->getTest()->getQuestions();
        asort($orderedIds);

        $positions = [];
        $pos = 0;
        $row = 0;
        foreach ($orderedIds as $oid) {
            $question = assQuestion::_instantiateQuestion($oid);

            if ($this->isQuestionTypeValid($question->getQuestionType())) {
                $positions[$oid] = $pos;
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
            $question = assQuestion::_instantiateQuestion($aid);

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
                            $answers = $this->getAnswersForLongMenuQuestions($solutions,
                                count($objQuestion->getAnswers()));
                        } elseif ($type === self::NUMERIC) {
                            $answers = $this->getAnswersForNumericQuestions($solutions);
                        }
                        $pos = $positions[$question["id"]];
                        $data_row[$col + $pos] = implode(",", $answers);
                    }
                }
            }

            $lv = getdate($data->getParticipant($active_id)->getLastVisit());
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
        $filesystem->write($relative_path, $writer->getCSVString());
    }

    protected function isQuestionTypeValid(string $type) : bool
    {
        $valid_types = [self::SINGLE_CHOICE, self::MULTIPLE_CHOICE, self::K_PRIM, self::LONG_MENU, self::NUMERIC];

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
                $answers[$pos] = '"' . $solutions[$i]["value2"] . '"';
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
            $answers[$i] = $solutions[$i]["value1"];
        }

        return $answers;
    }
}
