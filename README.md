# ImsmExport
ILIAS Plugin for exporting results for single/multiple choice questions in IMS format 

The Item Management System (IMS-m, https://www.ucan-assess.org/) is an external platform for managing exam questions. 
There, (mainly medical) questions can be managed, evaluated and shared.
 
IMS-m has itself been extended to allow tests created in IMS-m to be exported as an ILIAS test object.  
Currently the following question types are  supported:

* Typ A
* Pick-N
* Intervall
* Long Menu
* Typ KPrim

 
Once the test has been completed, the test results should be returned to the IMS-M platform 
so that statistics on the quality of the questions can be compiled. 
A special result file in CSV format can be exported within the test from the admin area "Export". 
It  does not contain personal data and can be further processed by the IMS-m software "Examinator".

* For ILIAS versions: 5.2.0 - 7.9.99


## Installation Instructions
1. Clone this repository 
   `$ git clone https://github.com/DatabayAG/ImsmExport.git`
2. Move the project to the ILIAS-plugin-directory
   `$ mv ImsmExport <ILIAS_DIRECTORY>/Customizing/global/plugins/Modules/Test/Export/ImsmExport`
3. Login to ILIAS with an administrator account (e.g. root)
4. Select **Plugins** from the **Administration** main menu drop down.
5. Search the **ImsmExport** plugin in the list of plugin and choose **Install** from the **Actions** drop down.
6. Search the **ImsmExport** plugin in the list of plugin and choose **Activate** from the **Actions** drop down.


## ImsmExport  View
![ImsmExport View](https://databayag.github.io/ImsmExport/IMSm_export.png)

## ImsmExport  File View
![ImsmExport File View](https://databayag.github.io/ImsmExport/imsm_export_file.png)
