# apfa-app
Process Flow Automation Framework based on microservice architecture concepts utilizing MVC+P pattern
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> ed2671541b184b5ed638a44366e454aede9caf95

## What is APFA framework:
Asiaus Process Flow Automation framerok. The prodject is divided into 3 sub-projects:

**_(1) Backend:_**_(private repo)_ Comprises of database (started with MySQL, other options to be included in future) and APIs to access various functions

**_(2) GUI Components:_**_(private repo)_ Microservice based html/js front-end where content will be ingested to create final _Interactive_ & _Responsive_ UI, easily

**_(3) Creator:_**_(public repo)_ The front-end to create customized process flows, GUI components for user interactions. **(This repository)**

**apfa-app** is the 3rd compoent being created and is currently available as public repository for open source development. First two compoents or their parts will be gradually moved to public.

## Functionalities of apfa-app

To access the build tool for Process Flow Automation use below:

**to start a new process design:** https://bestbn.asiaus.systems/apfa-app/ProcessFlow.php
  It starts the process flow with only two nodes: <START> and <END>, every activity data will have a `first_activity` that is link to the <START>
  
  Table in the content section has the list of all activities.
  
  Process designer can add activity or a gateway after a specific activity by clicking on the respective icon.
  Diagram can be refreshed by clicking on refresh icon.
  
  In future release Process Designer will be able to register and save data for retrieval
  - [ ] User login & registration
  - [ ] Ability to save / update the process data data by authorised user

**to start from an existing process:** https://bestbn.asiaus.systems/apfa-app/ProcessFlow.php/ `<process_id>`
  To start by using a saved process or a standard process template. (Currently system has only process id = 231)
  e.g. https://bestbn.asiaus.systems/apfa-app/ProcessFlow.php/231

`<process_id>` is the stored process in the database which can be retrieved by various APIs provided by the **_Backend_** component.

`https://bestbn.asiaus.systems/apfa/Process/getProcessFirstActivity/<process_id>` to get the first activity of the process (GET call)
  - [ ] To create process lists in the left pane of the page for easy access of <user processes> and <standard reference processes>
  - [ ] API call for getting the processes

`https://bestbn.asiaus.systems/apfa/Process/getProcessActivityData/<process_id>` to get the process data in json format (GET call)

Returns process data in the below format

```
{
    "status":"success",
    "data":
    [
        {
            "id":"1",
            "title":"Organization \/ Individual?",
            "is_gateway":"1",
            "activity_text":"Do you want to register as Individual or Organization? Please ensure that a person with significant interest in organization ha",
            "next_activity_id":"3",
            "next_activity_id_on_false":"2",
            "flow_direction":"0",
            "flow_direction_on_false":"90",
            "role_id":"60",
            "role":"Perpetual Associate"
        },
        {
            "id":"2",
            "title":"Org Details",
            "is_gateway":"0",
            "activity_text":"Provide Legal Details of the Organization and save data",
            "next_activity_id":"3",
            "next_activity_id_on_false":"2147483647",
            "flow_direction":"-45",
            "flow_direction_on_false":"0",
            "role_id":"60",
            "role":"Perpetual Associate"
        },
        ....
        ....
        ....
    ]
}
```
or 

```
{"status":"error","message":"Invalid Process ID <process_id>"}
```

`https://bestbn.asiaus.systems/apfa/Code/get/BPMN.php` default configuration (GET call)

    returns the php code to be executed

`https://bestbn.asiaus.systems/apfa/Process/getProcessDiagram` to get the process diagram by the process_data or activities_data are sent through POST call in json format (as above)

  returns the html code for display
  - [ ] to write code for each node to create form / views for process users
        
`http://bestbn.asiaus.systems/apfa/NodeGraphicsList/getCSS`
    get the CSS code for the process flow diagrams
    
