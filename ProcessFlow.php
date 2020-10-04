<?php
$user = posix_getpwuid(posix_getuid());
define('SERVER_HOME', $user['dir']);
define('APFA_LIBS', $user['dir'].'/public_html/bestbn/APFALib');

$apfa_lib = constant('APFA_LIBS');
require $apfa_lib."/BPMN.php";
require $apfa_lib."/NodeGraphics.php";
require $apfa_lib."/NodeGraphicsModel.php";
require $apfa_lib."/NodeGraphicsInstance.php";
require $apfa_lib."/ProcessNode.php";
require $apfa_lib."/Link.php";
require $apfa_lib."/Process.php";

$process_id = 231;
$ng_list = new NodeGraphics();
$process = new Process($process_id);

$first_activity = file_get_contents('https://bestbn.asiaus.systems/apfa/Process/getProcessFirstActivity/'.$process_id);

if (!first_activity)
{
    echo '<script language="javascript">';
    echo 'alert("Error in getting first activity for process '.$process_id.'. Please update manually)';
    echo '</script>';
    $first_activity = BPMN::MAX_INT_ID;
}

$activities = array(
    (object)array("id"=>"0", "title"=>"START", "is_gateway"=>"0", "activity_text"=>"START", "next_activity_id"=>$first_activity,
        "next_activity_id_on_false"=>"", "flow_direction"=>"0", "flow_direction_on_false"=>"",
        "role_id"=>"", "role"=>""),
    (object)array("id"=>"2147483647", "title"=>"END", "is_gateway"=>"0", "activity_text"=>"END", "next_activity_id"=>"",
        "next_activity_id_on_false"=>"", "flow_direction"=>"", "flow_direction_on_false"=>"",
        "role_id"=>"", "role"=>""));

$response = file_get_contents('https://bestbn.asiaus.systems/apfa/Process/getProcessActivityData/231');
$response_data = json_decode($response);

if ($response_data->status === "success")
{        
    $activities = array_merge($activities, $response_data->data);
}
else
{
    echo '<script language="javascript">';
    echo 'alert("Error in fetching Process Activity Data<hr>'.$response_data->status.':'.$response_data->message.'")';
    echo '</script>';
}

$json_activities = json_encode($activities);

// echo json_encode($process->getFlowData(231));

// var_dump(serialize($process->getFlowData(231)));

/*
$process_serialized = <<<EOT
EOT;

$process_serialized = serialize($process);
file_put_contents('file_after_serialization', $process_serialized);

$process_json_encoded_serialized = json_encode($process_serialized);
file_put_contents('file_after_json_serialization', $process_json_encoded_serialized);

$process_json_encoded_serialized = <<<EOT
"O:7:\"Process\":5:{s:2:\"id\";N;s:10:\"start_node\";O:11:\"ProcessNode\":9:{s:2:\"id\";i:0;s:5:\"title\";s:5:\"Start\";s:4:\"type\";i:11;s:9:\"next_node\";O:11:\"ProcessNode\":8:{s:2:\"id\";i:2147483647;s:5:\"title\";s:3:\"End\";s:4:\"type\";i:13;s:9:\"next_node\";N;s:13:\"previous_node\";N;s:18:\"next_node_on_false\";N;s:22:\"node_graphics_instance\";N;s:20:\"node_event_instances\";N;}s:13:\"previous_node\";N;s:18:\"next_node_on_false\";N;s:22:\"node_graphics_instance\";N;s:20:\"node_event_instances\";N;s:23:\"next_instance_direction\";i:0;}s:8:\"end_node\";r:7;s:14:\"\u0000Process\u0000nodes\";a:2:{i:0;r:3;i:2147483647;r:7;}s:14:\"\u0000Process\u0000links\";a:0:{}}"
EOT;

$process_serialized = json_decode($process_json_encoded_serialized);
//file_put_contents('file_after_json_decoded', $process_serialized);

$xprocess = unserialize($process_serialized);
/*
echo '<hr>';
var_dump($process_serialized1);
echo '<hr>';
var_dump($xprocess);
*/
$process->associateNodeGraphics($ng_list);
$process->drawNodes();
$process->drawLinks($ng_list);

$http = $process->getNodesHTTP().$process->getLinksHTTP();

// To check if the data matches with the objects
/*$links = array();
$params = array(&$links);
$traversed = array();
$travFunc2GetLinks = function(&$node, int $is_on_true, &$links) {
    if($is_on_true)
    {
        $links[(string)$node->id] = $node->next_node->id;
    }
    else
    {
        $links[$node->id.'false'] = $node->next_node_on_false->id;
    }
};
$process->traverseProcess($travFunc2GetLinks, $params);
*/
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style> 

div.top-bar {
    position: fixed; 
    top: 0px; 
    left: 0px; 
    width: 100%; 
    height: 90px;  
    border: 1px solid #d3d3d3;"
}

div.left-bar {
    position: fixed; 
    top: 91px; 
    left: 0px; 
    width: 20%; 
    height: 100%; 
    border: 1px solid #d3d3d3;
}

div.content-area {
    position: fixed; 
    top: 91px; 
    left: 20%; 
    width: 74%;
    height: calc(95% - 91px);
    overflow: auto;
    padding: 0% 2% 1% 2%;
    border: 1px solid #d3d3d3;
}

/*div.event {
	width: 40px;
    height: 40px;
	top: calc((100% - 40px)/2);
    border-radius: 21px;
}

div.activity {
    border-radius: 8px;
}

div.gateway {
    height: 35px;
    width: 35px;
    transform: rotate(45deg);
    transform-origin: 0% 0%;
    border-radius: 0px;
}
div.process-box:first-child {
    left: 20px;
    }
*/
p.process-node-text {
    margin: 0px;
    border: 0;
    color: black;
    background-color: rgba(255,255,255,0.85);
    padding: 0;
    width: calc(100%-4px);
    left:2px;
    right:2px;
    text-align: center;
    vertical-align: middle;
    position: absolute;
    top: 50%;
    transform: translate(0, -50%); 
    }

table {
    font-family: arial, sans-serif;
    font-size: 12px;
    padding: 5px;
    border-collapse: collapse;
    table-layout: fixed;
    word-wrap: break-word;
    width: auto;
    margin-left: auto;
    margin-right: auto;
}

td, th {
    border: 1px solid #cccccc;
}

th { text-align: center; background-color: #C8DDDB; font: bold white; padding: 5px}

td { text-align: left; background-color: #F4FBFA }

input[type=text].placeholder {
  background-color:#F6F6F6;
  color: #999;  
   font-weight: normal; 
}

table input[type=text], table input[type=number], table select {
  background-color: rgba(0,0,0,0);
  padding: 5px;
    border:none;
}

table input[type=number] { text-align: center; width: 82px; }
table select { width: 60px; } 

svg.button { fill: blue; background-color: rgba(0,0,0,0); }
svg.button:hover { fill: rgba(0,0,0,40); background-image: radial-gradient(circle, grey 10%, white, white); }

.svg-button { position: relative; display: inline-block; } 
.svg-button .suggestion-box 
{
    visibility: hidden;
    border: 1px solid black; border-radius: 5px; background: rgba(0,0,0,0.6); 
    color: white;
    position: absolute; left: 20px; top: -20px; width: 300%; padding: 2px;
    z-index:1;
}

.svg-button:hover .suggestion-box {visibility: visible; }

td .svg-button { top: 6px; }
/*    
p.gateway {
	transform: translate(0, -50%) rotate(-45deg);
}
*/

<?php echo $ng_list->publishCSS();?>

</style>
<link rel="stylesheet" type="text/css" href="css/apfa.css" media="screen"/>

</head>
<body>

<svg preserveAspectRatio="xMidYMid meet" style="display: none;">
    <symbol id="delete" viewBox="0 0 24 24">
        <path d="M14.12 10.47L12 12.59l-2.13-2.12l-1.41 1.41L10.59 14l-2.12 2.12l1.41 1.41L12 15.41l2.12 2.12l1.41-1.41L13.41 14l2.12-2.12l-1.41-1.41M15.5 4l-1-1h-5l-1 1H5v2h14V4h-3.5M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12M8 9h8v10H8V9z"></path>
    </symbol>
</svg>
<svg preserveAspectRatio="xMidYMid meet" style="display: none;">
    <symbol id="activity-add" viewBox="0 0 24 24">
        <path d="M21 15v3h3v2h-3v3h-2v-3h-3v-2h3v-3h2m-7 3H3V6h16v7h2V6c0-1.11-.89-2-2-2H3a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h11v-2z"></path>
    </symbol>
</svg>

<svg preserveAspectRatio="xMidYMid meet" style="display: none;">
    <symbol id="gateway-add" viewBox="0 0 24 24">
        <path d="M12 2
            c -.5 0 -1 .19 -1.41 .59 
            l -8 8 c -.79 .78 -.79 2.04 0 2.82
            l 8 8 c .78 .79 2.04.79 2.82 0
            l 0.75 -0.75 a 5.13 5.1 0 0 1 -1.5 -1.5 l -0.75 0.75 
            l -8 -8 l 8 -8 l 8 8 
            l -0.75 0.75 a 5.13 5.1 0 0 1 1.5 1.5 l 0.75 -0.75 
            c.79 -.78 .79 -2.04  0 -2.82
            l -8 -8 c -0.41 -.4 -.91 -0.59 -1.41 -.59
            M15.65 12.69v3h-3v2h3v3h2v-3h3v-2h-3v-3h-2z">
        </path>
</symbol>
</svg>

<?php
            $svg_delete = '
            <div class="svg-button">
                <svg class="button" width="24" height="24"><use xlink:href="#delete"></use></svg>
                <div class="suggestion-box">Delete</div>
            </div>';
            $svg_activity_add = '
            <div class="svg-button">
                <svg class="button" width="18" height="18"><use xlink:href="#activity-add"></use></svg>
                <div class="suggestion-box">Add activity</div>
            </div>';
            $svg_gateway_add = '
            <div class="svg-button">
                <svg class="button" width="18" height="18"><use xlink:href="#gateway-add"></use></svg>
                <div class="suggestion-box">Add gateway</div>
            </div>';
?>

<!--short form of above svg :- <path d="M12 2c-.5 0-1 .19-1.41.59l-8 8c-.79.78-.79 2.04 0 2.82l8 8c.78.79 2.04.79 2.82 0l.75-.75a5.13 5.1 0 0 1-1.5-1.5l-.75.75l-8-8l8-8l8 8l-.75.75a5.13 5.1 0 0 1 1.5 1.5l.75-.75c.79-.78.79-2.04  0-2.82l-8-8c-0.41-.4-.91-0.59-1.41-.59M15.65 12.69v3h-3v2h3v3h2v-3h3v-2h-3v-3h-2z"/> -->

<div class="top-bar">
</div>
<div class="left-bar">
    <form>
        <fieldset>
            <legend>Process Node:</legend>
            <label for="atitle">Title:</label>
            <input type="text" id="atitle" name="atitle"><br><br>
            <label for="atype">Type:</label>
            <input type="text" id="atype" name="atype"><br><br>
            <label for="next_node">Next Node:</label>
            <input type="text" id="next_node" name="next_node"><br><br>
            <label for="next_node_on_false">Next Node if false:</label>
            <input type="text" id="next_node_on_false" name="next_node_on_false"><br><br>
            <label for="next_instance_direction">Directiion of Next Node:</label>
            <input type="text" id="next_instance_direction" name="next_instance_direction"><br><br>
            <label for="next_instance_direction_on_false">Directiion of Next Node if False:</label>
            <input type="text" id="next_instance_direction_on_false" name="next_instance_direction_on_false"><br><br>
            <input type="submit" value="Submit">
        </fieldset>
    </form>
</div>
<div class="content-area">
    <div class="flow-container-outer">
        <!--?php print_r($links);?-->
        <?php 
        echo $http;
        //var_dump($process);
        ?>
    </div>
    <?php
        echo "<p><span style=\"color: #2a55cc;\"> Activities:</br></span></p>";
        echo '<table>
        <tbody>
            <tr>
                <th style="border: 0px; background-color: rgba(0,0,0,0);"></th>
                <th><strong>ID</strong></th>
                <th><strong>Title</strong></th>
                <th><strong>Is Gateway</strong></th>
                <th><strong>Next Id</strong></th>
                <th><strong>Next Id (False)</strong></th>
                <th><strong>Location <br> Next Box</strong></th>
                <th><strong>Location Next <br> Box (False)</strong></th>
            </tr>';

        foreach ($activities as $row)
        {
            //$row = (object)$row;
            echo '
            <tr>
                <td style="border: 0px; background-color: rgba(0,0,0,0);">';
            if ($row->id <> 0 && $row->id <> BPMN::MAX_INT_ID) echo $svg_delete;
            echo '
                </td>
                <td><input type="number" id="id'.$row->id.'" name=id'.$row->id.' min="0" max="'.BPMN::MAX_INT_ID.'"value="'.$row->id.'" /></td>
                <td><input type="text"  id="title'.$row->id.'" name=title'.$row->id.' value="'.$row->title.'" /></td>
                <td>
                    <select id="is_gateway'.$row->id.'" name=is_gateway'.$row->id.'>  
                    <option value="0" '.(($row->is_gateway==0)?'selected':'').'>No</option>
                            <option value="1" '.(($row->is_gateway==1)?'selected':'').'>Yes</option>
                        </select>
                </td>';
            echo '
                <td>';
            if($row->id <> BPMN::MAX_INT_ID)
            {
                echo '<input type="number" value='.$row->next_activity_id.' />';
                echo $svg_activity_add;
                echo $svg_gateway_add;
            };
            echo '
                <td>';
                    if ($row->is_gateway==1)
                    {
                        echo '<input type="number" value='.$row->next_activity_id_on_false.' />';
                        echo $svg_activity_add;
                        echo $svg_gateway_add;
                    }
                    {
                        echo '';
                    }
            echo '
                </td>
                <td>';
            if($row->id <> BPMN::MAX_INT_ID)
            {
                echo '<input type="number" value='.$row->flow_direction.' />';
            }
            echo '</td>
                <td>'.(($row->is_gateway==1)?'<input type="number" value='.$row->flow_direction_on_false.' />' : '').'</td>
            </tr>';
        }        
        echo '</tbody>
    </table></br>';
    ?>
    
    <div class="svg-button">
        <svg class="button" width="36" height="36"><use xlink:href="#activity-add"></use></svg>
        <div class="suggestion-box">Add activity</div>
    </div>
    <div class="svg-button">
        <svg class="button" width="36" height="36"><use xlink:href="#gateway-add"></use></svg>
        <div class="suggestion-box">Add gateway</div>
    </div>
</div>

</body>
</html>
