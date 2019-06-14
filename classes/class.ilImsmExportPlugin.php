<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/Test/classes/class.ilTestExportPlugin.php';

/**
 * Abstract parent class for all event hook plugin classes.
 * @author Michael Jansen <mjansen@databay.de>
 * @version $Id$
 * @ingroup ModulesTest
 */
class ilImsmExportPlugin extends ilTestExportPlugin
{
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
	protected function getFormatIdentifier()
	{
		return 'imsm';
	}

	/**
	 * @return string
	 */
	public function getFormatLabel()
	{
		return $this->txt('imsm_format');
	}

	/**
	 * @param ilTestExportFilename $filename
	 */
	protected function buildExportFile(ilTestExportFilename $filename)
	{
		$data = $this->getTest()->getCompleteEvaluationData(TRUE);
		$titles = $this->getTest()->getQuestionTitlesAndIndexes();
		$orderedIds = $this->getTest()->getQuestions();
		asort($orderedIds);

		$positions = array();
		$pos  = 0;
		$row  = 0;
		foreach($orderedIds as $oid)
		{
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

		foreach($titles as $aid => $title)
		{
			$question = assQuestion::_instantiateQuestion($aid);

			$is_sc = strcmp($question->getQuestionType(), 'assSingleChoice') == 0;
			$is_mc = strcmp($question->getQuestionType(), 'assMultipleChoice') == 0;
			if($is_sc || $is_mc)
			{
				$imsm_id = $question->getExternalId();
				$a_csv_header_row[$col + $positions[$aid]] = $imsm_id;
			}
		}
		$a_csv_header_row[count($a_csv_header_row)] = 'time';

		// fill csv body
		$a_csv_data_rows = array();
		$a_csv_data_rows[$row++] = $a_csv_header_row;

		foreach($data->getParticipants() as $active_id => $userdata)
		{
			$a_csv_row = array();
			$col = 0;
			$anon_id = $row;
			$a_csv_row[$col++] = "lastname_" . $anon_id;
			$a_csv_row[$col++] = "firstname_" . $anon_id;
			$a_csv_row[$col++] = "1111" . $anon_id;
			$a_csv_row[$col++] = "id_" . $anon_id;

			$pass = $userdata->getScoredPass();
			if(is_object($userdata) && is_array($userdata->getQuestions($pass)))
			{
				foreach($userdata->getQuestions($pass) as $question)
				{
					$objQuestion = assQuestion::_instantiateQuestion($question["id"]);
						
					$is_sc = strcmp($objQuestion->getQuestionType(), 'assSingleChoice') == 0;
					$is_mc = strcmp($objQuestion->getQuestionType(), 'assMultipleChoice') == 0;
					if(is_object($objQuestion) && ($is_sc || $is_mc))
					{
						$solutions = $objQuestion->getSolutionValues($active_id, $pass);
						$answers = array();
						for($i = 0; $i < count($solutions); $i++)
						{
							$selectedanswer = chr(65 + $solutions[$i]["value1"]);
							array_push($answers, $selectedanswer);
						}
						sort($answers);
						$pos = $positions[$question["id"]];
						$a_csv_row[$col + $pos] = implode($answers, ",");
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
		foreach($a_csv_data_rows as $evalrow)
		{
			$csvrow = $this->getTest()->processCSVRow($evalrow, FALSE, $separator);
			$csv .= join($csvrow, $separator) . "\n";
		}

		ilUtil::makeDirParents(dirname($filename->getPathname('ims', 'ims')));
		file_put_contents($filename->getPathname('ims', 'ims'), $csv);
	}
}