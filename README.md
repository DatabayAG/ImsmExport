# ImsmExport

ILIAS Plugin for exporting results for the questions in IMS format

The Item Management System (IMS-m, https://www.ucan-assess.org/) is an external platform for managing exam questions.
There, (mainly medical) questions can be managed, evaluated and shared.

IMS-m has itself been extended to allow tests created in IMS-m to be exported as an ILIAS test object.  
Currently the following question types are  supported:

* Typ A
* Pick-N
* Intervall
* Long Menu
* Typ KPrim
* Essay Question

Once the test has been completed, the test results should be returned to the IMS-M platform so that statistics on the quality of the questions can be compiled. A special result file in CSV format can be exported within the test "Participants" tab. The dropdown "ExportData" offers the format "IMSm (CSV)" .The assessment identifier from the IMS-M platform is integrated into the CSV file name, simplifying data managment and transfer. The resulting file can then be further processed by the IMS-M software "Examinator".

By default, the export does not contain personal data. A configuration page in the plugin administration of ILIAS allows the ILIAS-administrators to use checkboxes to specify which personal data from an ILIAS user account should be included in the export.

## Installation Instructions

1. Clone this repository 
   `$ git clone https://github.com/DatabayAG/ImsmExport.git`
2. Move the project to the ILIAS-plugin-directory
   `$ mv ImsmExport <ILIAS_DIRECTORY>/public/Customizing/global/plugins/Modules/Test/Export/ImsmExport`
3. run `composer du` in the ILIAS directory
4. Login to ILIAS with an administrator account (e.g. root)
5. Select **Plugins** from the **Administration** main menu drop down.
6Search the **ImsmExport** plugin in the list of plugin and choose **Install** from the **Actions** drop down.
7Search the **ImsmExport** plugin in the list of plugin and choose **Activate** from the **Actions** drop down.

## File Example

| last_name | first_name | Matrikel | user | IMSm-i43024216q43024216 | IMSm-i39659215q39659215 | IMSm-i39659411q39659411 | IMSm-i42407402q42407402 | IMSm-i39659454q39659454 | IMSm-i39659233q39659233 | time |
|------------|-------------|-----------|-------|--------------------------|-------------------------|--------------------------|-----------------------|--------------------------|--------------------------|------------------|
| user | root | 12345678 | root | A,C,F | Aachen,Köln             | A | Text with ☺️| 15 | A+,B+,C-,D- | 19.12.2025 11:41:17 |