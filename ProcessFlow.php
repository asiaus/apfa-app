<?php
$url = isset($_SERVER['PATH_INFO']) ? explode('/', ltrim($_SERVER['PATH_INFO'],'/')) : '/';
$process_id = ($url=='/')?null:$url[0];

$response = file_get_contents('https://bestbn.asiaus.systems/apfa/Code/get/BPMN.php');
$response_decoded = json_decode($response);

if ($response_decoded->status === 'success')
{
    $phpcode = $response_decoded->data;
    eval($phpcode);
}

$first_activity = BPMN::MAX_INT_ID;
$activities_data = array();

if (!is_null($process_id))
{
    $temp = file_get_contents('https://bestbn.asiaus.systems/apfa/Process/getProcessFirstActivity/'.$process_id);

    if (!$temp)
    {
        echo '<script language="javascript">';
        echo 'alert("Error in getting first activity for process '.$process_id.'. Please update manually")';
        echo '</script>';
    }
    else
    {
        $first_activity = $temp;
    }

    $response = file_get_contents('https://bestbn.asiaus.systems/apfa/Process/getProcessActivityData/'.$process_id);
    $response_data = json_decode($response);

    if ($response_data->status === "success" && !is_null($response_data->data))
    {
        $activities_data = $response_data->data;
    }
    else
    {
        echo '<script language="javascript">';
        echo 'alert("Error in fetching Process Activities Data<hr>'.$response_data->status.':'.$response_data->message.'")';
        echo '</script>';
    }
}

$start_activities = array(
    (object)array("id"=>"0", "title"=>"START", "is_gateway"=>"0", "activity_text"=>"START", "next_activity_id"=>$first_activity,
        "next_activity_id_on_false"=>"", "flow_direction"=>"0", "flow_direction_on_false"=>"",
        "role_id"=>"", "role"=>""));

$end_activities = array((object)array("id"=>"2147483647", "title"=>"END", "is_gateway"=>"0", "activity_text"=>"END", "next_activity_id"=>"",
        "next_activity_id_on_false"=>"", "flow_direction"=>"", "flow_direction_on_false"=>"",
        "role_id"=>"", "role"=>""));

$activities = array_merge($start_activities, $activities_data, $end_activities);
$json_activities = json_encode($activities);

$url = 'https://bestbn.asiaus.systems/apfa/Process/getProcessDiagram';
$data = json_encode($activities_data);
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

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

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
        <path d="M12 2c-.5 0-1 .19-1.41.59l-8 8c-.79.78-.79 2.04 0 2.82l8 8c.78.79 2.04.79 2.82 0l.75-.75a5.13 5.1 0 0 1-1.5-1.5l-.75.75l-8-8l8-8l8 8l-.75.75a5.13 5.1 0 0 1 1.5 1.5l.75-.75c.79-.78.79-2.04  0-2.82l-8-8c-0.41-.4-.91-0.59-1.41-.59M15.65 12.69v3h-3v2h3v3h2v-3h3v-2h-3v-3h-2z">
	</path>
</symbol>
</svg>

<svg preserveAspectRatio="xMidYMid meet" style="display: none;">
    <symbol id="chart-refresh" viewBox="0 0 16 16">
	<path d="M2.6 5.6C3.5 3.5 5.6 2 8 2c3 0 5.4 2.2 5.9 5h2c-.5-3.9-3.8-7-7.9-7c-3 0-5.6 1.6-6.9 4.1L0 3v4h4L2.6 5.6z"/><path d="M16 9h-4.1l1.5 1.4c-.9 2.1-3 3.6-5.5 3.6C5 14 2.5 11.8 2 9H0c.5 3.9 3.9 7 7.9 7c3 0 5.6-1.7 7-4.1L16 13V9z">
	</path>
    </symbol>
</svg>

<?php
    $svg_delete = '
    <div class="svg-button delete">
        <svg class="button" width="24" height="24"><use xlink:href="#delete"></use></svg>
        <div class="suggestion-box">Delete</div>
    </div>';
    $svg_activity_add = '
    <div class="svg-button activity-add">
        <svg class="button" width="18" height="18"><use xlink:href="#activity-add"></use></svg>
        <div class="suggestion-box">Add activity</div>
    </div>';
    $svg_gateway_add = '
    <div class="svg-button gateway-add">
        <svg class="button" width="18" height="18"><use xlink:href="#gateway-add"></use></svg>
        <div class="suggestion-box">Add gateway</div>
    </div>';
    $svg_chart_refresh = '
    <div class="svg-button chart-refresh">
        <svg class="button" width="24" height="24"><use xlink:href="#chart-refresh"></use></svg>
        <div class="suggestion-box">Refresh Chart</div>
    </div>';
?>

<div class="top-bar" style="text-align: center;">
<b>APFA: </b>A simple approach to create complex workflow automation
<hr>
Create your own workflow charts for you business or personal needs. Useit in your own website. Download simple json code for future reuse. Develop it interactively or by writing simple code.<br>
- <b> Current status:</b> UI concept and backend is designed<br>
- <b> Future Plans:</b> Simple and easy form creation for each BPM actiovity<br>
</div>

<div class="left-bar">
    <div style="color: #2a55cc;"> 
        <h3>Your Process Library:</h3>
    </div>
    <div style="color: #2a55cc;"> 
        <h3>Reference Process Library:</h3>
    </div>
    <!--form>
        <fieldset>
            <legend>Process Node:</legend>
            <label for="atitle">Title:</label>
            <input type="text" id="atitle" name="atitle"><br><br>
            <label for="atype">Type:</label>
            <input type="text" id="atype" name="atype"><br><br>
            <label for="next_activity">Next Node:</label>
            <input type="text" id="next_activity" name="next_activity"><br><br>
            <label for="next_activity_on_false">Next Node if false:</label>
            <input type="text" id="next_activity_on_false" name="next_activity_on_false"><br><br>
            <label for="next_instance_direction">Directiion of Next Node:</label>
            <input type="text" id="next_instance_direction" name="next_instance_direction"><br><br>
            <label for="next_instance_direction_on_false">Directiion of Next Node if False:</label>
            <input type="text" id="next_instance_direction_on_false" name="next_instance_direction_on_false"><br><br>
            <input type="submit" value="Submit">
        </fieldset>
    </form-->
</div>

<div id="content-area-0" class="content-area">
    <div style="position: sticky; top:20px; left:10px; z-index:2;">
	<?echo $svg_chart_refresh;?>
    </div>
    <div id="content-flow-chart" class="flow-container-outer">
        <span><!--?php print_r($links);?--></span>
        <?php 
        echo $http;
        //var_dump($process);
        ?>
    </div>
    <?php
        echo "<p><span style=\"color: #2a55cc;\"> Activities:</br></span></p>";
        echo '<table id="table-activities">
        <tbody id="tbody-activities">
            <tr id="title-row">
                <th style="border: 0px; background-color: rgba(0,0,0,0);"></th>
                <th><strong>ID</strong></th>
                <th><strong>Title</strong></th>
                <th><strong>Is Gateway</strong></th>
                <th><strong>Next Id</strong></th>
                <th><strong>Next Id (False)</strong></th>
                <th><strong>Location Next Box</strong></th>
                <th><strong>Location Next Box<br>(False)</strong></th>
            </tr>';

        foreach ($activities as $row)
        {
            //$row = (object)$row;
            echo '
            <tr id="row_id-'.$row->id.'">
                <td style="border: 0px; background-color: rgba(0,0,0,0);">';
            if ($row->id <> 0 && $row->id <> BPMN::MAX_INT_ID) echo $svg_delete;
            echo '
                </td>
                <td><input type="number" id="id-'.$row->id.'" name=id-'.$row->id.' min="0" max="'.BPMN::MAX_INT_ID.'"value="'.$row->id.'" /></td>
                <td><input type="text"  id="title-'.$row->id.'" name=title-'.$row->id.' value="'.$row->title.'" /></td>
                <td>
                    <select id="is_gateway-'.$row->id.'" name=is_gateway-'.$row->id.'>  
                        <option value="0" '.(($row->is_gateway==0)?'selected':'').'>No</option>
                        <option value="1" '.(($row->is_gateway==1)?'selected':'').'>Yes</option>
                    </select>
                </td>';
            echo '
                <td>';
            if($row->id <> BPMN::MAX_INT_ID)
            {
                echo '<input type="number" id="next_activity_id-'.$row->id.'" name=next_activity_id-'.$row->id.' value='.$row->next_activity_id.' />';
                echo $svg_activity_add;
                echo $svg_gateway_add;
            };
            echo '
                <td>';
            if ($row->is_gateway==1)
            {
                echo '<input type="number" id="next_activity_id_on_false-'.$row->id.'" name=next_activity_id_on_false-'.$row->id.' value='.$row->next_activity_id_on_false.' />';
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
                echo '<select id="flow_direction-'.$row->id.'" name=flow_direction-'.$row->id.'>
	                <option value=0 '.(($row->flow_direction==0)?'selected':'').'> Right (&rarr;)
                    <option value=45 '.(($row->flow_direction==45)?'selected':'').'> Right Up (&rarr;&uarr;)
                    <option value=-315 '.(($row->flow_direction==-315)?'selected':'').'> Up Right (&#8625;)
                    <option value=90 '.(($row->flow_direction==90)?'selected':'').'> Up (&uarr;)
                    <option value=135 '.(($row->flow_direction==135)?'selected':'').'> Up Left (&#8624;)
                    <option value=-225 '.(($row->flow_direction==-225)?'selected':'').'> Left Up (&uarr;&larr;)
                    <option value=180 '.(($row->flow_direction==180)?'selected':'').'> Left (&larr;)
                    <option value=225 '.(($row->flow_direction==225)?'selected':'').'> Left Down (&darr;&larr;)
                    <option value=-135 '.(($row->flow_direction==-135)?'selected':'').'> Down Left(&#8629;)
                    <option value=270 '.(($row->flow_direction==270)?'selected':'').'> Down (&darr;)
                    <option value=315 '.(($row->flow_direction==315)?'selected':'').'> Down Right (&#8627;)
                    <option value=-45 '.(($row->flow_direction==-45)?'selected':'').'> Right Down (&#8628;)
                </select>';
            }
            echo '</td>
                <td>';
	    if($row->is_gateway==1)
	    {
		    echo '<select id="flow_direction_on_false-'.$row->id.'" name=flow_direction_on_false-'.$row->id.'>
                <option value=0 '.(($row->flow_direction_on_false==0)?'selected':'').'> Right (&rarr;)
                <option value=45 '.(($row->flow_direction_on_false==45)?'selected':'').'> Right Up (&rarr;&uarr;)
                <option value=-315 '.(($row->flow_direction_on_false==-315)?'selected':'').'> Up Right (&#8625;)
                <option value=90 '.(($row->flow_direction_on_false==90)?'selected':'').'> Up (&uarr;)
                <option value=135 '.(($row->flow_direction_on_false==135)?'selected':'').'> Up Left (&#8624;)
                <option value=-225 '.(($row->flow_direction_on_false==-225)?'selected':'').'> Left Up (&uarr;&larr;)
                <option value=180 '.(($row->flow_direction_on_false==180)?'selected':'').'> Left (&larr;)
                <option value=225 '.(($row->flow_direction_on_false==225)?'selected':'').'> Left Down (&darr;&larr;)
                <option value=-135 '.(($row->flow_direction_on_false==-135)?'selected':'').'> Down Left(&#8629;)
                <option value=270 '.(($row->flow_direction_on_false==270)?'selected':'').'> Down (&darr;)
                <option value=315 '.(($row->flow_direction_on_false==315)?'selected':'').'> Down Right (&#8627;)
                <option value=-45 '.(($row->flow_direction_on_false==-45)?'selected':'').'> Right Down (&#8628;)
            </select>';
	    }
 	    echo '</td>
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

<script>
const MAX_INT_ID = <?echo BPMN::MAX_INT_ID;?>;
class ProcessNode {
    constructor(id = null, title = null, is_gateway = null, next_activity_id = null, next_activity_id_on_false = null, flow_direction = null, flow_direction_on_false = null) {
        this.id = (id?parseInt(id):id);
        this.title = title;
        this.is_gateway = is_gateway?parseInt(is_gateway):is_gateway;
        this.next_activity_id = next_activity_id?parseInt(next_activity_id):next_activity_id;
        this.next_activity_id_on_false = next_activity_id_on_false?parseInt(next_activity_id_on_false):next_activity_id_on_false;
        this.flow_direction = flow_direction?parseInt(flow_direction):flow_direction;
        this.flow_direction_on_false = flow_direction_on_false?parseInt(flow_direction_on_false):flow_direction_on_false;
    }
}

function createNodeFromRowCells(rc)
{
    if(parseInt(rc[2].value))
    {
	return(new ProcessNode(rc[0].value, rc[1].value, rc[2].value, rc[3].value, rc[4].value, rc[5].value, rc[6].value));
    }
    else
    {
	if (rc[0].value == MAX_INT_ID)
	{
	    return(new ProcessNode(rc[0].value, rc[1].value, rc[2].value));	
	}
	else
	{
            return(new ProcessNode(rc[0].value, rc[1].value, rc[2].value, rc[3].value, null, rc[4].value, null));
	}
    }
}

function createNodeList(context_tbody)
{
    acTVTs = [];
    row = $(context_tbody).children("tr:nth-child(3)");
    while (row.length > 0)
    {
        var rc = row.find("input, select");
        if (rc[0].value != MAX_INT_ID)
        {
            acTVTs.push(createNodeFromRowCells(rc));
        }
        row = row.next();
    }
    return acTVTs;
}

$(document).ready(function(){
    context_tbody = $("div#content-area-0").find("tbody");
    node_list = createNodeList(context_tbody);
    max_node_id = 0;
    jQuery.each(node_list, function(i, val){
        if (val.id > max_node_id) max_node_id = val.id;
    });

    func_on_change = function(){
        node_list = createNodeList(context_tbody);
    };

    func_delete = function(){
        cur_row = $(this).parentsUntil("tbody", "tr");
        row_cells = cur_row.find("input, select");

        //Array.prototype.forEach.call(row_cells, cell => {alert(cell.value);});

        cur_node = createNodeFromRowCells(row_cells);
        // alert(cur_node.id + ' ' + cur_node.title  + ' ' + cur_node.is_gateway + ' ' + cur_node.next_activity_id + ' ' + cur_node.next_activity_id_on_false + ' ' + cur_node.flow_direction + ' ' + cur_node.flow_direction_on_false);

        if (cur_node.next_activity_id == cur_node.id) cur_node.next_activity_id = MAX_INT_ID;
        if (cur_node.next_activity_id_on_false == cur_node.id) cur_node.next_activity_id = MAX_INT_ID;
        
        if(cur_node.is_gateway && cur_node.next_activity_id != MAX_INT_ID && cur_node.next_activity_id_on_false != MAX_INT_ID)
        {
            alert('Can not delete gateway. Please delete the subsequent activities on one of the leg');
            return;
        }
        
        if(cur_node.is_gateway) {
            next_row = cur_row.siblings("#row_id-" + ((cur_node.next_activity_id != MAX_INT_ID)?cur_node.next_activity_id:cur_node.next_activity_id_on_false));
        } else {
            next_row = cur_row.siblings("#row_id-" + cur_node.next_activity_id);
        }
        
        next_activity = createNodeFromRowCells(next_row.find("input, select"));
        
        Array.prototype.forEach.call(cur_row.siblings().find("[id^=next_activity]"), act_ref_cell => {
            if (act_ref_cell.value == cur_node.id) act_ref_cell.value = next_activity.id;
        });

    	/* to create undo-redo pattern */

        cur_row.remove();
        node_list = createNodeList(context_tbody);
    };

    func_add_activity = function(){
        cur_row = $(this).parentsUntil("tbody", "tr");
        row_cells = cur_row.find("input, select");
        
        id = ++max_node_id;
        
        cur_node = createNodeFromRowCells(row_cells);

        next_node_id = $(this).prev()[0].value;
        $(this).prev()[0].value = id;
        
        cur_row.after(`
            <tr id="row_id-`+id+`">
                <td style="border: 0px; background-color: rgba(0,0,0,0);">
                    `+<?echo "`".$svg_delete."`";?>+`
                </td>
                <td>
                    <input type="number" id="id-`+id+`" name=id-`+id+` min="0" max="`+<?echo "`".BPMN::MAX_INT_ID."`";?>+`"value="`+id+`" />
                </td>
                <td>
                    <input type="text"  id="title-`+id+`" name=title-`+id+` value="" />
                </td>
                <td>
                    <select id="is_gateway-`+id+`" name=is_gateway-`+id+`>
                        <option value="0" selected>No</option>
                        <option value="1">Yes</option>
                    </select>
                </td>
                <td>
                    <input type="number" id="next_activity_id-`+id+`" name=next_activity_id-`+id+` value=`+next_node_id+` />
                    `+<?echo "`".$svg_activity_add."`";?>+`
                    `+<?echo "`".$svg_gateway_add."`";?>+`
                </td>
                <td>
                </td>
                <td>
                    <select id="flow_direction-`+id+`" name=flow_direction-`+id+`>
                        <option value=0 selected> Right (&rarr;)
                        <option value=45> Right Up (&rarr;&uarr;)
                        <option value=-315> Up Right (&#8625;)
                        <option value=90> Up (&uarr;)
                        <option value=135> Up Left (&#8624;)
                        <option value=-225> Left Up (&uarr;&larr;)
                        <option value=180> Left (&larr;)
                        <option value=225> Left Down (&darr;&larr;)
                        <option value=-135> Down Left(&#8629;)
                        <option value=270> Down (&darr;)
                        <option value=315> Down Right (&#8627;)
                        <option value=-45> Right Down (&#8628;)
                    </select>
                </td>
                <td>
                </td>
            </tr>
        `);
        new_row = cur_row.next();
        new_row.find("select, input").change(func_on_change);
        new_row.find(".svg-button.delete").click(func_delete);
        new_row.find(".svg-button.activity-add").click(func_add_activity);
        new_row.find(".svg-button.gateway-add").click(func_add_gateway);
    };

    func_add_gateway = function(){
        cur_row = $(this).parentsUntil("tbody", "tr");
        row_cells = cur_row.find("input, select");
        
        id = ++max_node_id;
        
        cur_node = createNodeFromRowCells(row_cells);

        next_node_id = $(this).prev().prev()[0].value;
        $(this).prev().prev()[0].value = id;
        
        cur_row.after(`
            <tr id="row_id-`+id+`">
                <td style="border: 0px; background-color: rgba(0,0,0,0);">
                    `+<?echo "`".$svg_delete."`";?>+`
                </td>
                <td>
                    <input type="number" id="id-`+id+`" name=id-`+id+` min="0" max="`+<?echo "`".BPMN::MAX_INT_ID."`";?>+`"value="`+id+`" />
                </td>
                <td>
                    <input type="text"  id="title-`+id+`" name=title-`+id+` value="" />
                </td>
                <td>
                    <select id="is_gateway-`+id+`" name=is_gateway-`+id+`>
                        <option value="0">No</option>
                        <option value="1" selected>Yes</option>
                    </select>
                </td>
                <td>
                    <input type="number" id="next_activity_id-`+id+`" name=next_activity_id-`+id+` value=`+next_node_id+` />
                    `+<?echo "`".$svg_activity_add."`";?>+`
                    `+<?echo "`".$svg_gateway_add."`";?>+`
                </td>
                <td>
                    <input type="number" id="next_activity_id_on_false-`+id+`" name=next_activity_id_on_false-`+id+` value=`+next_node_id+` />
                    `+<?echo "`".$svg_activity_add."`";?>+`
                    `+<?echo "`".$svg_gateway_add."`";?>+`
                </td>
                <td>
                    <select id="flow_direction-`+id+`" name=flow_direction-`+id+`>
                        <option value=0> Right (&rarr;)
                        <option value=45 selected> Right Up (&rarr;&uarr;)
                        <option value=-315> Up Right (&#8625;)
                        <option value=90> Up (&uarr;)
                        <option value=135> Up Left (&#8624;)
                        <option value=-225> Left Up (&uarr;&larr;)
                        <option value=180> Left (&larr;)
                        <option value=225> Left Down (&darr;&larr;)
                        <option value=-135> Down Left(&#8629;)
                        <option value=270> Down (&darr;)
                        <option value=315> Down Right (&#8627;)
                        <option value=-45> Right Down (&#8628;)
                    </select>
                </td>
                <td>
                    <select id="flow_direction_on_false-`+id+`" name=flow_direction_on_false-`+id+`>
                        <option value=0> Right (&rarr;)
                        <option value=45> Right Up (&rarr;&uarr;)
                        <option value=-315 selected> Up Right (&#8625;)
                        <option value=90> Up (&uarr;)
                        <option value=135> Up Left (&#8624;)
                        <option value=-225> Left Up (&uarr;&larr;)
                        <option value=180> Left (&larr;)
                        <option value=225> Left Down (&darr;&larr;)
                        <option value=-135> Down Left(&#8629;)
                        <option value=270> Down (&darr;)
                        <option value=315> Down Right (&#8627;)
                        <option value=-45> Right Down (&#8628;)
                    </select>
                </td>
            </tr>
        `);
        new_row = cur_row.next();
        new_row.find("select, input").change(func_on_change);
        new_row.find(".svg-button.delete").click(func_delete);
        new_row.find(".svg-button.activity-add").click(func_add_activity);
        new_row.find(".svg-button.gateway-add").click(func_add_gateway);
    };

    func_refresh_chart = function(){
        //context_tbody = $(this).parent().parent().find("tbody");
        acTVTs_json = JSON.stringify(node_list);
        $.post('https://bestbn.asiaus.systems/apfa/Process/getProcessDiagram', acTVTs_json, function(){} ,"html")
	    .done(function(result, status, xhr)
	    {
		$("div.flow-container-outer").empty().append(result); 
		alert("Chart Refreshed \n\nResult: " + status + "\n xhr Status: " + xhr.status + " " + xhr.statusText);
	    })
            .fail(function(xhr, status, error){alert("Inconsistent Data:\n\n" + status + " " + error + " " + xhr.status + " " + xhr.statusText)});
    };

    context_tbody.find("select, input").change(func_on_change);

    $(".content-area .svg-button.delete").click(func_delete);

    $(".content-area .svg-button.activity-add").click(func_add_activity); 

    $(".content-area .svg-button.gateway-add").click(func_add_gateway);

    $(".content-area .svg-button.chart-refresh").click(func_refresh_chart);
});
</script>

</body>
</html>
