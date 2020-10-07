<?php
$response = file_get_contents('https://bestbn.asiaus.systems/apfa/Code/get/BPMN.php');
$response_decoded = json_decode($response);

if ($response_decoded->status === 'success')
{
    $phpcode = $response_decoded->data;
    eval($phpcode);
}

$process_id = 231;

$first_activity = file_get_contents('https://bestbn.asiaus.systems/apfa/Process/getProcessFirstActivity/'.$process_id);

if (!$first_activity)
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

$response = file_get_contents('https://bestbn.asiaus.systems/apfa/Process/getProcessActivityData/'.$process_id);
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

$url = 'https://bestbn.asiaus.systems/apfa/Process/getProcessDiagram';
$data = json_encode($response_data->data);
$context_options = array(
    			'http' => array(
            			'method'  => 'POST',
            			'header'  => 'Content-type: application/x-www-form-urlencoded',
            			'content' => $data)
			);

$http = file_get_contents($url, false, stream_context_create($context_options));

?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style> 

<?php 
$fileList = array( 'apfa.css', 'container-0-home.css', 'container-1-navbar.css', 'container-2-navbar-logo.css', 'container-3-menu.css', 'container-4-navbar-info.css', 'container-5-navbar-loginform.css', 'container-6-table.css', 'container-7-svg-button.css');

$dirPath = 'css/';
foreach($fileList as $fileName)
{
    include_once($dirPath.$fileName);
}

$node_graphics_css = json_decode(file_get_contents('http://bestbn.asiaus.systems/apfa/NodeGraphicsList/getCSS'));
if ($node_graphics_css->status === "success")
{
    echo $node_graphics_css->data;
}
?>

</style>

<!--link rel="stylesheet" type="text/css" href="css/apfa.css" media="screen"/ -->

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
        <path d="M12 2c-.5 0-1 .19-1.41.59l-8 8c-.79.78-.79 2.04 0 2.82l8 8c.78.79 2.04.79 2.82 0l.75-.75a5.13 5.1 0 0 1-1.5-1.5l-.75.75l-8-8l8-8l8 8l-.75.75a5.13 5.1 0 0 1 1.5 1.5l.75-.75c.79-.78.79-2.04  0-2.82l-8-8c-0.41-.4-.91-0.59-1.41-.59M15.65 12.69v3h-3v2h3v3h2v-3h3v-2h-3v-3h-2z"/>
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
