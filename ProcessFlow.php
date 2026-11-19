<?php
declare(strict_types=1);

// Never let PHP warnings reach the page.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// BPMN constants are loaded locally. Fetching and eval()-ing code from the API is gone (S3).
require_once dirname(__DIR__) . '/bootstrap.php';

use APFA\Api\Auth\CreatorSession;
use APFA\Engine\BPMN;

// Standalone installs: the creator needs a login session (APFA_CREATOR_PASSWORD_HASH). Its writes
// then authenticate with that session cookie plus a CSRF token, never with an API key in JS.
// Without the setting a host application is expected to sign users in (APFA_HOST_BOOTSTRAP).
$creator_login = CreatorSession::isEnabled();
$csrf_token = null;
if ($creator_login) {
    CreatorSession::start();
    if (!CreatorSession::isAuthenticated()) {
        CreatorSession::close();
        $login_url = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/apfa-app/ProcessFlow.php'))), '/') . '/login.php';
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $return_to = $script . (string) ($_SERVER['PATH_INFO'] ?? '');
        header('Location: ' . $login_url . '?next=' . rawurlencode($return_to), true, 303);
        exit;
    }
    $csrf_token = CreatorSession::csrfToken();
    CreatorSession::close();
}

/** Escape a value for HTML text or a quoted attribute. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Encode a value as a JavaScript literal that is safe inside a <script> block. */
function js($value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/** An opaque tenant key (1-64 characters of A-Z a-z 0-9 _ . : -), or null. */
function to_tenant_id($value): ?string
{
    if (!is_string($value) || preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $value) !== 1) {
        return null;
    }
    return $value;
}

/** A non-negative integer id, or null. */
function to_id($value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $id === false ? null : $id;
}

/** Example shown in empty gateway condition fields. */
const CONDITION_PLACEHOLDER = '{"var":"amount","op":"gt","value":1000}';

$api_base_url = apfa_api_base_url();                    // used by the browser
$internal_api_base_url = apfa_internal_api_base_url();  // used by this page's server-side calls

// URL: ProcessFlow.php/{tenant_id}/{process_id}. A single segment is a process id in the
// tenant named by APFA_DEFAULT_TENANT_ID (D1: the tenant is required by the API).
$segments = array_values(array_filter(explode('/', trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/')), static fn (string $s): bool => $s !== ''));
$tenant_id = null;
$process_id = null;
if (count($segments) >= 2) {
    $tenant_id = to_tenant_id($segments[0]);
    $process_id = to_id($segments[1]);
} elseif (count($segments) === 1) {
    $tenant_id = to_tenant_id(apfa_env('APFA_DEFAULT_TENANT_ID'));
    $process_id = to_id($segments[0]);
}

$first_activity = BPMN::MAX_INT_ID;
$activities_data = array();

if (!is_null($tenant_id) && !is_null($process_id))
{
    // D1: the creator loads through the active getProcessData endpoint, with drafts overlaid,
    // so what was saved as a draft is what is shown.
    $response = file_get_contents($internal_api_base_url.'/Process/getProcessData/'.rawurlencode($tenant_id).'/'.$process_id.'/draft');
    $response_data = json_decode((string) $response);

    if (($response_data->status ?? null) === "success")
    {
        $first_activity = $response_data->data->first_node ?? BPMN::MAX_INT_ID;
        $node_list = $response_data->data->node_list ?? null;
        $activities_data = is_array($node_list) ? $node_list : array();
    }
    else
    {
        echo '<script language="javascript">';
        echo 'alert('.js('Error in fetching process data: '.($response_data->status ?? 'error').': '.($response_data->message ?? 'no response')).')';
        echo '</script>';
    }
}
elseif (!is_null($process_id))
{
    echo '<script language="javascript">';
    echo 'alert('.js('A tenant id is required. Open ProcessFlow.php/{tenant_id}/{process_id}.').')';
    echo '</script>';
}

$start_activities = array(
    (object)array("id"=>"0", "title"=>"START", "is_gateway"=>"0", "activity_text"=>"START", "next_activity_id"=>$first_activity,
        "next_activity_id_on_false"=>"", "flow_direction"=>"0", "flow_direction_on_false"=>"",
        "role_key"=>"", "role"=>""));

$end_activities = array((object)array("id"=>"2147483647", "title"=>"END", "is_gateway"=>"0", "activity_text"=>"END", "next_activity_id"=>"",
        "next_activity_id_on_false"=>"", "flow_direction"=>"", "flow_direction_on_false"=>"",
        "role_key"=>"", "role"=>""));

$activities = array_merge($start_activities, $activities_data, $end_activities);
$json_activities = json_encode($activities);

$url = $internal_api_base_url.'/Process/getProcessDiagram';
$data = json_encode($activities_data);
$context_options = array(
    			'http' => array(
            			'method'  => 'POST',
            			'header'  => 'Content-Type: application/json',
            			'content' => $data)
			);

$http = (string) file_get_contents($url, false, stream_context_create($context_options));

?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="data:,">
<style> 

<?php 
$fileList = array( 'apfa.css', 'container-0-home.css', 'container-6-table.css', 'container-7-svg-button.css');

$dirPath = 'css/';
foreach($fileList as $fileName)
{
    include_once(__DIR__.'/'.$dirPath.$fileName);
}

$node_graphics_css = json_decode((string) file_get_contents($internal_api_base_url.'/NodeGraphicsList/getCSS'));
if (($node_graphics_css->status ?? null) === "success" && is_string($node_graphics_css->data ?? null))
{
    // CSS never contains "<"; dropping it stops a response from closing the <style> element.
    echo str_replace('<', '', $node_graphics_css->data);
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
	<?= $svg_chart_refresh /* static markup defined above, no user data */ ?>
<?php $no_target = (is_null($tenant_id) || is_null($process_id)) ? ' disabled title="Open ProcessFlow.php/{tenant_id}/{process_id} first"' : ''; ?>
	<button type="button" id="save-draft" class="button"<?= $no_target ?>>Save draft</button>
	<button type="button" id="publish-process" class="button"<?= $no_target ?>>Publish</button>
	<button type="button" id="clone-from-library" class="button"<?= $no_target ?>>Clone from library</button>
	<span id="dirty-flag" class="dirty-flag" hidden>Unsaved changes</span>
<?php if ($creator_login): ?>
	<form method="post" action="<?= e(rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/apfa-app/ProcessFlow.php'))), '/') . '/login.php') ?>" style="display:inline">
	    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
	    <input type="hidden" name="action" value="logout">
	    <button type="submit" id="sign-out" class="button">Sign out</button>
	</form>
<?php endif; ?>
	<button type="button" id="problem-count" class="problem-count" hidden title="Show the problems under the chart"></button>
    </div>
    <div id="content-flow-chart" class="flow-container-outer">
        <span><!--?php print_r($links);?--></span>
        <?php 
        echo $http;
        //var_dump($process);
        ?>
    </div>
    <?php
        echo '<div id="validation-banner" class="validation-banner" role="status" aria-live="polite" hidden></div>';
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
                <th><strong>Condition</strong><br>(gateway, JSON rule)</th>
            </tr>';

        foreach ($activities as $row)
        {
            //$row = (object)$row;
            echo '
            <tr id="row_id-'.e($row->id).'">
                <td style="border: 0px; background-color: rgba(0,0,0,0);">';
            if ($row->id <> 0 && $row->id <> BPMN::MAX_INT_ID) echo $svg_delete;
            echo '
                </td>
                <td><input type="number" id="id-'.e($row->id).'" name="id-'.e($row->id).'" min="0" max="'.BPMN::MAX_INT_ID.'" value="'.e($row->id).'" /></td>
                <td><input type="text"  id="title-'.e($row->id).'" name="title-'.e($row->id).'" value="'.e($row->title).'" /></td>
                <td>
                    <select id="is_gateway-'.e($row->id).'" name="is_gateway-'.e($row->id).'">  
                        <option value="0" '.(($row->is_gateway==0)?'selected':'').'>No</option>
                        <option value="1" '.(($row->is_gateway==1)?'selected':'').'>Yes</option>
                    </select>
                </td>';
            echo '
                <td>';
            if($row->id <> BPMN::MAX_INT_ID)
            {
                echo '<input type="number" id="next_activity_id-'.e($row->id).'" name="next_activity_id-'.e($row->id).'" value="'.e($row->next_activity_id).'" />';
                echo $svg_activity_add;
                echo $svg_gateway_add;
            };
            echo '
                <td>';
            if ($row->is_gateway==1)
            {
                echo '<input type="number" id="next_activity_id_on_false-'.e($row->id).'" name="next_activity_id_on_false-'.e($row->id).'" value="'.e($row->next_activity_id_on_false).'" />';
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
                echo '<select id="flow_direction-'.e($row->id).'" name="flow_direction-'.e($row->id).'">
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
		    echo '<select id="flow_direction_on_false-'.e($row->id).'" name="flow_direction_on_false-'.e($row->id).'">
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
                <td>';
            if ($row->is_gateway==1)
            {
                // UC-07: declarative rule for the TRUE branch (see APFALib/Runtime/ConditionEvaluator.php).
                echo '<input type="text" class="condition-expr" id="condition_expr-'.e($row->id).'" name="condition_expr-'.e($row->id).'" maxlength="512" placeholder="e.g. '.e(CONDITION_PLACEHOLDER).'" value="'.e($row->condition_expr ?? '').'" />';
            }
            echo '</td>
            </tr>';
        }        
        echo '</tbody>
    </table></br>';
    ?>
    
    <div class="svg-button" id="append-activity" role="button" tabindex="0">
        <svg class="button" width="36" height="36"><use xlink:href="#activity-add"></use></svg>
        <div class="suggestion-box">Add activity at the end</div>
    </div>
    <div class="svg-button" id="append-gateway" role="button" tabindex="0">
        <svg class="button" width="36" height="36"><use xlink:href="#gateway-add"></use></svg>
        <div class="suggestion-box">Add gateway at the end</div>
    </div>
</div>

<script>
const MAX_INT_ID = <?= (int) BPMN::MAX_INT_ID ?>;
const APFA_API_BASE_URL = <?= js($api_base_url) ?>;
const TENANT_ID = <?= js($tenant_id) ?>;
const PROCESS_ID = <?= js($process_id) ?>;
// Per-session CSRF token (null when a host application handles sign-in). Not a credential:
// the API also needs the HttpOnly session cookie, which scripts cannot read.
const CSRF_TOKEN = <?= js($csrf_token) ?>;
const CONDITION_PLACEHOLDER = <?= js(CONDITION_PLACEHOLDER) ?>;
var deleted_node_ids = [];
class ProcessNode {
    constructor(id = null, title = null, is_gateway = null, next_activity_id = null, next_activity_id_on_false = null, flow_direction = null, flow_direction_on_false = null, condition_expr = undefined) {
        this.id = (id?parseInt(id):id);
        this.title = title;
        this.is_gateway = is_gateway?parseInt(is_gateway):is_gateway;
        this.next_activity_id = next_activity_id?parseInt(next_activity_id):next_activity_id;
        this.next_activity_id_on_false = next_activity_id_on_false?parseInt(next_activity_id_on_false):next_activity_id_on_false;
        this.flow_direction = flow_direction?parseInt(flow_direction):flow_direction;
        this.flow_direction_on_false = flow_direction_on_false?parseInt(flow_direction_on_false):flow_direction_on_false;
        // Only gateway rows carry a condition; undefined leaves the field out of the JSON.
        if (condition_expr !== undefined) { this.condition_expr = condition_expr; }
    }
}

// The value of a row's field, found by its name prefix (e.g. "title-") rather than by column
// position, so rows with and without the gateway columns read the same way. undefined = no field.
function rowField(row, name) {
    var field = $(row).find('[name^="' + name + '-"]').filter(function(){
        return this.name.slice(0, name.length + 1) === name + '-';
    }).first();
    return field.length ? field.val() : undefined;
}

function createNodeFromRow(row)
{
    var id = rowField(row, 'id');
    var is_gateway = rowField(row, 'is_gateway');
    if (id == MAX_INT_ID)
    {
        return(new ProcessNode(id, rowField(row, 'title'), is_gateway));
    }
    if (parseInt(is_gateway))
    {
        var condition = rowField(row, 'condition_expr');
        return(new ProcessNode(id, rowField(row, 'title'), is_gateway,
                               rowField(row, 'next_activity_id'), rowField(row, 'next_activity_id_on_false'),
                               rowField(row, 'flow_direction'), rowField(row, 'flow_direction_on_false'),
                               condition === undefined ? undefined : condition.trim()));
    }
    return(new ProcessNode(id, rowField(row, 'title'), is_gateway, rowField(row, 'next_activity_id'), null,
                           rowField(row, 'flow_direction'), null));
}

// Kept for callers that pass a row's inputs: the row is looked up from the first cell.
function createNodeFromRowCells(rc)
{
    return createNodeFromRow($(rc).first().closest("tr"));
}

// All task and gateway rows (START and END are sentinels owned by the engine).
function nodeRows(context_tbody)
{
    return $(context_tbody).children("tr[id^=row_id-]").filter(function(){
        return this.id !== 'row_id-0' && this.id !== 'row_id-' + MAX_INT_ID;
    });
}

function createNodeList(context_tbody)
{
    return nodeRows(context_tbody).map(function(){ return createNodeFromRow(this); }).get();
}

$(document).ready(function(){
    context_tbody = $("div#content-area-0").find("tbody");
    node_list = createNodeList(context_tbody);
    max_node_id = 0;
    var dirty = false;
    var selected_node_id = null;

    // A new id is one higher than any id in the table or removed in this session, so it is
    // never reused before the next save (UC-06).
    function nextNodeId() {
        nodeRows(context_tbody).each(function(){
            var id = parseInt(rowField(this, 'id'), 10);
            if (!isNaN(id) && id > max_node_id) { max_node_id = id; }
        });
        deleted_node_ids.forEach(function(id){ if (id > max_node_id) { max_node_id = id; } });
        return ++max_node_id;
    }

    function setDirty(value) {
        dirty = value;
        $("#dirty-flag").prop("hidden", !value);
    }

    function tableChanged() {
        node_list = createNodeList(context_tbody);
        setDirty(true);
        renderValidation(validateTable());
    }

    func_on_change = function(){ tableChanged(); };

    function linkValue(input) {
        var value = parseInt($(input).val(), 10);
        return isNaN(value) ? MAX_INT_ID : value;
    }

    // UC-06: remove a row and point every link to it at its successor, so no link dangles.
    // A gateway can go only when at most one branch leads somewhere other than END (or both
    // branches lead to the same node); otherwise one leg would be orphaned.
    func_delete = function(){
        var cur_row = $(this).closest("tr");
        var cur_node = createNodeFromRow(cur_row);
        var next_id = linkValue(cur_row.find('[name^="next_activity_id-"]'));
        var next_id_on_false = cur_node.is_gateway ? linkValue(cur_row.find('[name^="next_activity_id_on_false-"]')) : MAX_INT_ID;
        if (next_id == cur_node.id) next_id = MAX_INT_ID;                       // a self-loop leads nowhere
        if (next_id_on_false == cur_node.id) next_id_on_false = MAX_INT_ID;     // D10

        var successor = next_id;
        if (cur_node.is_gateway)
        {
            if (next_id != MAX_INT_ID && next_id_on_false != MAX_INT_ID && next_id != next_id_on_false)
            {
                alert('Cannot delete gateway ' + cur_node.id + ': both branches lead to other nodes. Delete the activities on one branch first.');
                return;
            }
            successor = (next_id != MAX_INT_ID) ? next_id : next_id_on_false;
        }

        cur_row.siblings().find('input[name^="next_activity_id"]').each(function(){
            if (parseInt(this.value, 10) === cur_node.id) { this.value = successor; }
        });

        /* to create undo-redo pattern */

        if (selected_node_id === cur_node.id) { clearSelection(); }
        cur_row.remove();
        if (cur_node.id && deleted_node_ids.indexOf(cur_node.id) < 0) deleted_node_ids.push(cur_node.id);
        tableChanged();
    };

    // Flow directions offered in the "Location Next Box" selects (same values as the rows the
    // server renders; see BPMN.php for the angle convention).
    const FLOW_DIRECTIONS = [
        [0, 'Right (→)'], [45, 'Right Up (→↑)'], [-315, 'Up Right (↱)'], [90, 'Up (↑)'],
        [135, 'Up Left (↰)'], [-225, 'Left Up (↑←)'], [180, 'Left (←)'], [225, 'Left Down (↓←)'],
        [-135, 'Down Left (↵)'], [270, 'Down (↓)'], [315, 'Down Right (↳)'], [-45, 'Right Down (↴)']
    ];
    const SVG_DELETE = <?= js($svg_delete) ?>;
    const SVG_ACTIVITY_ADD = <?= js($svg_activity_add) ?>;
    const SVG_GATEWAY_ADD = <?= js($svg_gateway_add) ?>;

    function directionSelect(name, id, selected) {
        var select = $('<select>', {id: name + '-' + id, name: name + '-' + id});
        FLOW_DIRECTIONS.forEach(function(d){
            select.append($('<option>', {value: d[0], text: d[1], selected: d[0] === selected}));
        });
        return select;
    }

    function nextCell(name, id, target) {
        return $('<td>')
            .append($('<input>', {type: 'number', id: name + '-' + id, name: name + '-' + id, min: 0, max: MAX_INT_ID, value: target}))
            .append(SVG_ACTIVITY_ADD)
            .append(SVG_GATEWAY_ADD);
    }

    // A table row in the same shape as the rows rendered by PHP above.
    // Titles are set through jQuery (text/value), never concatenated into HTML.
    function buildNodeRow(id, is_gateway, next_id, next_id_on_false) {
        var row = $('<tr>', {id: 'row_id-' + id});
        row.append($('<td>', {style: 'border: 0px; background-color: rgba(0,0,0,0);'}).append(SVG_DELETE));
        row.append($('<td>').append($('<input>', {type: 'number', id: 'id-' + id, name: 'id-' + id, min: 0, max: MAX_INT_ID, value: id})));
        row.append($('<td>').append($('<input>', {type: 'text', id: 'title-' + id, name: 'title-' + id,
            value: (is_gateway ? 'New gateway ' : 'New activity ') + id})));
        var gateway_select = $('<select>', {id: 'is_gateway-' + id, name: 'is_gateway-' + id});
        gateway_select.append($('<option>', {value: 0, text: 'No', selected: !is_gateway}));
        gateway_select.append($('<option>', {value: 1, text: 'Yes', selected: is_gateway}));
        // The false-branch columns exist only on gateway rows, so a new row keeps its type
        // (delete it and add the other kind instead). A disabled select still has its value.
        gateway_select.prop('disabled', true).attr('title', 'Delete the row and add the other kind to change it');
        row.append($('<td>').append(gateway_select));
        row.append(nextCell('next_activity_id', id, next_id));
        row.append(is_gateway ? nextCell('next_activity_id_on_false', id, next_id_on_false) : $('<td>'));
        row.append($('<td>').append(directionSelect('flow_direction', id, 0)));
        row.append($('<td>').append(is_gateway ? directionSelect('flow_direction_on_false', id, 270) : null));
        row.append($('<td>').append(is_gateway ? $('<input>', {type: 'text', 'class': 'condition-expr', id: 'condition_expr-' + id,
            name: 'condition_expr-' + id, maxlength: 512, placeholder: 'e.g. ' + CONDITION_PLACEHOLDER, value: ''}) : null));
        return row;
    }

    function wireRow(row) {
        row.find("select, input").change(func_on_change);
        row.find(".svg-button.delete").click(func_delete);
        row.find(".svg-button.activity-add").click(func_add_activity);
        row.find(".svg-button.gateway-add").click(func_add_gateway);
    }

    // UC-06: the "+" buttons sit right after a next-node input. The new node gets the next free id,
    // its row goes right after the clicked row, the clicked link now points to it, and it points
    // to the old target (on both branches for a gateway).
    function insertOnLink(button, is_gateway) {
        var link_input = $(button).prevAll("input").first();
        var cur_row = $(button).closest("tr");
        if (link_input.length === 0 || cur_row.length === 0) { return; }
        var id = nextNodeId();
        var old_target = linkValue(link_input);
        link_input.val(id);
        var new_row = buildNodeRow(id, is_gateway, old_target, old_target);
        cur_row.after(new_row);
        wireRow(new_row);
        tableChanged();
        selectNode(id, 'table');
        new_row.find("#title-" + id).focus().select();
    }

    // The large buttons under the table append a node at the end of the flow: every link that
    // pointed to END now points to the new node, which points to END.
    function appendBeforeEnd(is_gateway) {
        var id = nextNodeId();
        context_tbody.find("input[id^=next_activity_id]").each(function(){
            if (parseInt(this.value, 10) === MAX_INT_ID) { this.value = id; }
        });
        var new_row = buildNodeRow(id, is_gateway, MAX_INT_ID, MAX_INT_ID);
        context_tbody.children("#row_id-" + MAX_INT_ID).before(new_row);
        wireRow(new_row);
        tableChanged();
        selectNode(id, 'table');
        new_row.find("#title-" + id).focus().select();
    }

    func_add_activity = function(){ insertOnLink(this, false); };
    func_add_gateway = function(){ insertOnLink(this, true); };

    // ---------------------------------------------------------------------------------------
    // UC-12: two-way selection between the diagram (#node{id}) and the table (#row_id-{id}).
    // ---------------------------------------------------------------------------------------
    function rowForNode(id) {
        return context_tbody.children("tr[id^=row_id-]").filter(function(){
            return parseInt(rowField(this, 'id'), 10) === id;
        }).first();
    }

    function clearSelection() {
        selected_node_id = null;
        $("#content-flow-chart .process-box.apfa-selected").removeClass("apfa-selected");
        context_tbody.children("tr.apfa-selected").removeClass("apfa-selected");
    }

    // from: 'diagram' scrolls the table to the row; 'table' scrolls the diagram to the box.
    function selectNode(id, from) {
        if (isNaN(id)) { return; }
        clearSelection();
        selected_node_id = id;
        var row = rowForNode(id);
        var box = $("#content-flow-chart").find("#node" + id);
        row.addClass("apfa-selected");
        box.addClass("apfa-selected");
        if (from === 'diagram' && row.length) {
            row[0].scrollIntoView({block: 'center', behavior: 'smooth'});
        }
        if (from === 'table' && box.length) {
            var chart = document.getElementById("content-flow-chart");
            var el = box[0];
            chart.scrollTo({left: Math.max(0, el.offsetLeft - chart.clientWidth / 2 + el.offsetWidth / 2),
                            top: Math.max(0, el.offsetTop - chart.clientHeight / 2 + el.offsetHeight / 2),
                            behavior: 'smooth'});
        }
    }

    $("#content-flow-chart").on("click", ".process-box", function(){
        var id = parseInt(this.id.replace(/^node/, ''), 10);
        selectNode(id, 'diagram');
    });

    context_tbody.on("click focusin", "tr[id^=row_id-]", function(event){
        if ($(event.target).closest(".svg-button").length) { return; }   // add/delete buttons handle themselves
        var id = parseInt(rowField(this, 'id'), 10);
        if (id !== selected_node_id) { selectNode(id, 'table'); }
    });

    // ---------------------------------------------------------------------------------------
    // UC-13: validation of the table as it is now. It runs after every change and before Publish,
    // and the server checks the saved draft again when publishing.
    // ---------------------------------------------------------------------------------------
    function validateTable() {
        var problems = [];
        var rows = nodeRows(context_tbody);
        var nodes = {};
        rows.each(function(){
            var id = parseInt(rowField(this, 'id'), 10);
            var title = rowField(this, 'title') || '';
            if (isNaN(id) || id <= 0 || id >= MAX_INT_ID) {
                problems.push({node: null, text: 'Row "' + title + '" has no valid id.'});
                return;
            }
            if (nodes[id]) {
                problems.push({node: id, text: 'Id ' + id + ' is used by more than one row.'});
                return;
            }
            var is_gateway = parseInt(rowField(this, 'is_gateway'), 10) === 1;
            var next = rowField(this, 'next_activity_id');
            var next_on_false = rowField(this, 'next_activity_id_on_false');
            nodes[id] = {
                title: title,
                is_gateway: is_gateway,
                next: (next === undefined || next === '') ? null : parseInt(next, 10),
                next_on_false: (next_on_false === undefined || next_on_false === '') ? null : parseInt(next_on_false, 10),
                condition: rowField(this, 'condition_expr')
            };
        });
        var label = function(id){ return (nodes[id] && nodes[id].title ? '"' + nodes[id].title + '" (' + id + ')' : 'Node ' + id); };
        var exists = function(id){ return id === MAX_INT_ID || nodes[id] !== undefined; };

        var first = $("#next_activity_id-0").val();
        var first_id = (first === undefined || first === '') ? null : parseInt(first, 10);
        if (first_id === null || isNaN(first_id)) {
            problems.push({node: null, text: 'START has no next node.'});
        } else if (!exists(first_id)) {
            problems.push({node: null, text: 'START points to node ' + first_id + ', which does not exist.'});
        }

        Object.keys(nodes).forEach(function(key){
            var id = parseInt(key, 10), n = nodes[key];
            if (n.next === null || isNaN(n.next)) {
                problems.push({node: id, text: label(id) + ' has no next node (Next Id is empty).'});
            } else if (!exists(n.next)) {
                problems.push({node: id, text: label(id) + ' points to node ' + n.next + ', which does not exist.'});
            }
            if (n.is_gateway) {
                if (n.next_on_false === null || isNaN(n.next_on_false)) {
                    problems.push({node: id, text: 'Gateway ' + label(id) + ' has no FALSE branch (Next Id (False) is empty).'});
                } else if (!exists(n.next_on_false)) {
                    problems.push({node: id, text: 'Gateway ' + label(id) + ' FALSE branch points to node ' + n.next_on_false + ', which does not exist.'});
                }
                if (n.condition !== undefined && n.condition.trim() !== '') {
                    var rule = null;
                    try { rule = JSON.parse(n.condition); } catch (e) { rule = null; }
                    if (rule === null || typeof rule !== 'object' || Array.isArray(rule)) {
                        problems.push({node: id, text: 'Gateway ' + label(id) + ' condition is not a JSON rule object, e.g. ' + CONDITION_PLACEHOLDER});
                    }
                }
            }
        });

        // Reachability from START, following both gateway branches.
        var seen = {}, queue = (first_id !== null && !isNaN(first_id)) ? [first_id] : [];
        while (queue.length) {
            var cur = queue.shift();
            if (seen[cur] || !nodes[cur]) { continue; }
            seen[cur] = true;
            [nodes[cur].next, nodes[cur].is_gateway ? nodes[cur].next_on_false : null].forEach(function(t){
                if (t !== null && !isNaN(t) && !seen[t]) { queue.push(t); }
            });
        }
        Object.keys(nodes).forEach(function(key){
            var id = parseInt(key, 10);
            if (!seen[id]) {
                problems.push({node: id, text: label(id) + ' cannot be reached from START.'});
            }
        });
        return problems;
    }

    // The banner sits right under the sticky chart, so it is in view at the top of the page.
    function showBanner() { window.scrollTo({top: 0, behavior: 'smooth'}); }
    $("#problem-count").click(showBanner);

    // problems: [{node: id|null, text}]. Clicking a problem selects its node.
    function renderValidation(problems, heading) {
        var banner = $("#validation-banner").empty().removeClass("ok warn");
        $("#problem-count").text(problems.length + (problems.length === 1 ? ' problem' : ' problems')).prop("hidden", problems.length === 0);
        if (problems.length === 0) {
            banner.addClass("ok").text(heading || "Ready to publish: no problems found in the table.").prop("hidden", false);
            return;
        }
        banner.addClass("warn").prop("hidden", false);
        banner.append($('<strong>').text(heading || (problems.length + (problems.length === 1 ? ' problem' : ' problems') + ' to fix before publishing:')));
        var list = $('<ul>');
        problems.forEach(function(p){
            var item = $('<li>').text(p.text);
            if (p.node !== null && p.node !== undefined) {
                item.addClass("clickable").attr("title", "Show this node").on("click", function(){ selectNode(p.node, 'table'); selectNode(p.node, 'diagram'); });
            }
            list.append(item);
        });
        banner.append(list);
    }

    // The server's 422 errors ([{code, node_key|from, message}]) in the same banner.
    function renderServerErrors(heading, errors) {
        renderValidation((errors || []).map(function(e){
            var key = e.node_key !== undefined ? e.node_key : e.from;
            var id = parseInt(key, 10);
            var where = (key === undefined) ? '' : ('Node ' + key + ': ');
            return {node: isNaN(id) ? null : id, text: where + (e.message || e.code)
                + (e.to !== undefined ? ' (' + e.to + ')' : '')};
        }), heading);
        if (!errors || errors.length === 0) {
            $("#validation-banner").removeClass("ok").addClass("warn");
        }
    }

    func_refresh_chart = function(){
        node_list = createNodeList(context_tbody);   // include rows added or edited since the last change event
        acTVTs_json = JSON.stringify(node_list);
        $.post(APFA_API_BASE_URL + '/Process/getProcessDiagram', acTVTs_json, function(){} ,"html")
	    .done(function(result, status, xhr)
	    {
		$("div.flow-container-outer").empty().append(result);
		if (selected_node_id !== null) { selectNode(selected_node_id, 'table'); }   // keep the selection on the new chart
		var dangling = xhr.getResponseHeader('X-APFA-Dangling-Links');
		renderValidation(validateTable());
		alert("Chart Refreshed \n\nResult: " + status + "\n xhr Status: " + xhr.status + " " + xhr.statusText
		      + (dangling ? "\n\n" + dangling + " link(s) point to missing nodes and are drawn to END: see the warnings above the chart." : ""));
	    })
            .fail(function(xhr, status, error){alert("Inconsistent Data:\n\n" + status + " " + error + " " + xhr.status + " " + xhr.statusText)});
    };

    // POST a JSON write to the API with the session's CSRF token. A 401 means the login session
    // expired: send the user to the login page and back to this process afterwards. Validation
    // errors (422 with an errors list) are shown in the banner as well.
    function apfaWrite(action, payload, failure_text) {
        var headers = {};
        if (CSRF_TOKEN !== null) { headers['X-CSRF-Token'] = CSRF_TOKEN; }
        return $.ajax({
            url: APFA_API_BASE_URL + '/Process/' + action,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: headers,
            data: JSON.stringify(payload)
        })
        .fail(function(xhr){
            var body = xhr.responseJSON || {};
            var message = body.message ? body.message : (xhr.status + " " + xhr.statusText);
            if (xhr.status === 401 && CSRF_TOKEN !== null) {
                alert(failure_text + ": " + message);
                window.location.reload();
                return;
            }
            if (Array.isArray(body.errors) && body.errors.length) {
                renderServerErrors(failure_text + ': ' + message, body.errors);
                showBanner();
            }
            alert(failure_text + ": " + message);
        });
    }

    // D1: save the current table as a draft. Every node is sent as modified, rows removed
    // with the delete button are sent as deleted_nodes, and the START row's next node as first_node.
    function saveDraft() {
        node_list = createNodeList(context_tbody);
        var first_node = parseInt($("#next_activity_id-0").val(), 10);
        var payload = {
            tenant_id: TENANT_ID,
            process_id: PROCESS_ID,
            first_node: isNaN(first_node) ? MAX_INT_ID : first_node,
            nodes: node_list.map(function(n){ return Object.assign({}, n, {modified: true}); }),
            deleted_nodes: deleted_node_ids.filter(function(id){
                return !node_list.some(function(n){ return n.id == id; });
            })
        };
        return apfaWrite('saveProcessData', payload, "Save failed")
        .done(function(){
            deleted_node_ids = [];
            setDirty(false);
        });
    }

    func_save_draft = function(){
        if (TENANT_ID === null || PROCESS_ID === null) {
            alert("Open ProcessFlow.php/{tenant_id}/{process_id} to save.");
            return;
        }
        saveDraft().done(function(result){ alert(result.message || "Saved"); });
    };

    // UC-13: check the table first and stop with the problems listed in the banner; the server
    // validates the saved draft again (links, FALSE branches, reachability, conditions).
    // Unsaved edits are saved as the draft before publishing, after the user agrees.
    func_publish = function(){
        if (TENANT_ID === null || PROCESS_ID === null) { return; }
        var problems = validateTable();
        renderValidation(problems);
        if (problems.length) {
            showBanner();
            return;
        }
        var question = dirty
            ? "The table has unsaved changes. Save them as the draft and publish process " + PROCESS_ID + "?"
            : "Publish the saved draft of process " + PROCESS_ID + "?";
        if (!confirm(question)) { return; }
        var publish = function(){
            apfaWrite('publishProcess', {tenant_id: TENANT_ID, process_id: PROCESS_ID}, "Publish failed")
            .done(function(result){
                renderValidation([], "Published" + (result.data && result.data.version ? " as version " + result.data.version : "") + ".");
                alert(result.message || "Published");
            });
        };
        if (dirty) { saveDraft().done(publish); } else { publish(); }
    };

    // Copy the library version of this process into the tenant as a new draft, then reload it.
    func_clone = function(){
        if (TENANT_ID === null || PROCESS_ID === null) { return; }
        apfaWrite('cloneFromLibrary', {tenant_id: TENANT_ID, process_id: PROCESS_ID}, "Clone failed")
        .done(function(result){
            alert(result.message || "Cloned");
            window.location.reload();
        });
    };

    context_tbody.find("select, input").change(func_on_change);

    $(".content-area .svg-button.delete").click(func_delete);

    $(".content-area .svg-button.activity-add").click(func_add_activity);

    $(".content-area .svg-button.gateway-add").click(func_add_gateway);

    $(".content-area .svg-button.chart-refresh").click(func_refresh_chart);

    $("#append-activity").click(function(){ appendBeforeEnd(false); });

    $("#append-gateway").click(function(){ appendBeforeEnd(true); });

    $("#save-draft").click(func_save_draft);

    $("#publish-process").click(func_publish);

    $("#clone-from-library").click(func_clone);

    renderValidation(validateTable());

    $(window).on("beforeunload", function(){ return dirty ? true : undefined; });
});
</script>

</body>
</html>
