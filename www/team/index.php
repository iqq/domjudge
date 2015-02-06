<?php
/**
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

require('init.php');
$title = htmlspecialchars($teamdata['name']);
require(LIBWWWDIR . '/header.php');

// Don't use HTTP meta refresh, but javascript: otherwise we cannot
// cancel it when the user starts editing the submit form. This also
// provides graceful degradation without javascript present.
$refreshtime = 30;

$submitted = @$_GET['submitted'];

$fdata = calcFreezeData($cdata);
$langdata = $DB->q('KEYTABLE SELECT langid AS ARRAYKEY, name, extensions
		    FROM language WHERE allow_submit = 1');

echo "<script type=\"text/javascript\">\n<!--\n";

if ( $fdata['cstarted'] ) {
	$extra = "";
	if ( dbconfig_get('separate_start_end',0) ) {
		$extra = " AND (start < NOW() OR start IS NULL)
			   AND (end   > NOW() or end   IS NULL) ";
	}
	$probdata = $DB->q('TABLE SELECT probid, shortname, name FROM problem
			    INNER JOIN contestproblem USING (probid)
			    WHERE cid = %i AND allow_submit = 1 ' . $extra . '
			    ORDER BY shortname', $cid);

	putgetMainExtension($langdata);

	echo "function getProbDescription(probid)\n{\n";
	echo "\tswitch(probid) {\n";
	foreach($probdata as $probinfo) {
		echo "\t\tcase '" . htmlspecialchars($probinfo['shortname']) .
		    "': return '" . htmlspecialchars($probinfo['name']) . "';\n";
	}
	echo "\t\tdefault: return '';\n\t}\n}\n\n";
}

echo "initReload(" . $refreshtime . ");\n";
echo "// -->\n</script>\n";

echo "<br/><br/>\n\n";

echo "<div id=\"submitlist\">\n";

echo "<h3 class=\"teamoverview\">Submissions</h3>\n\n";


if ( $fdata['cstarted'] ) {
	if ( $submitted ) {
		echo "<p class=\"submissiondone\">submission done <a href=\"./\">x</a></p>\n\n";
	} else {
		$maxfiles = dbconfig_get('sourcefiles_limit',100);

		echo addForm('upload.php','post',null,'multipart/form-data', null,
		             ' onreset="resetUploadForm('.$refreshtime .', '.$maxfiles.');"') .
		    "<p id=\"submitform\">\n\n";

		echo "<input type=\"file\" name=\"code[]\" id=\"maincode\" required";
		if ( $maxfiles > 1 ) {
			echo " multiple";
		}
		echo " />\n";


		$probs = array();
		foreach($probdata as $probinfo) {
			$probs[$probinfo['probid']]=$probinfo['shortname'];
		}
		$probs[''] = 'problem';
		echo addSelect('probid', $probs, '', true);
		$langs = array();
		foreach($langdata as $langid => $langdata) {
			$langs[$langid] = $langdata['name'];
		}
		$langs[''] = 'language';
		echo addSelect('langid', $langs, '', true);

		echo addSubmit('submit', 'submit',
			       "return checkUploadForm();");

		echo addReset('cancel');

		if ( $maxfiles > 1 ) {
			echo "<br /><span id=\"auxfiles\"></span>\n" .
			    "<input type=\"button\" name=\"addfile\" id=\"addfile\" " .
			    "value=\"Add another file\" onclick=\"addFileUpload();\" " .
			    "disabled=\"disabled\" />\n";
		}
		echo "<script type=\"text/javascript\">initFileUploads($maxfiles);</script>\n\n";

		echo "</p>\n</form>\n\n";
	}
}


// call putSubmissions function from common.php for this team.
$restrictions = array( 'teamid' => $teamid );
putSubmissions(array($cdata['cid'] => $cdata), $restrictions, null, $submitted);


?>
<div style="text-align:center;">
	<span id="showsubs" style="display:none;color:#50508f;font-weight:bold;" onclick="showAllSubmissions(true)">all submissions</span>
</div>
<script language="javascript">
	function showAllSubmissions(show) {
		var css = document.createElement("style");
		css.type = "text/css";
		showsubs = document.getElementById('showsubs');
		if (show) {
			showsubs.style.display = "none";
			css.innerHTML = ".old { display: table-row; }";
		} else {
			showsubs.style.display = "inline";
			css.innerHTML = ".old { display: none; }";
		}
		document.body.appendChild(css);
	}
	showAllSubmissions(false);
</script> 
<?php

echo "<h3 class=\"teamoverview\">Stats</h3>\n\n";

putSolvedUnsolved($teamid, $cid);

echo "</div>\n\n";

echo "<div id=\"clarlist\">\n";

$requests = $DB->q('SELECT c.*, cp.shortname, t.name AS toname, f.name AS fromname
                    FROM clarification c
                    LEFT JOIN problem p USING(probid)
                    LEFT JOIN contestproblem cp USING (probid, cid)
                    LEFT JOIN team t ON (t.teamid = c.recipient)
                    LEFT JOIN team f ON (f.teamid = c.sender)
                    WHERE c.cid = %i AND c.sender = %i
                    ORDER BY submittime DESC, clarid DESC', $cid, $teamid);

$clarifications = $DB->q('SELECT c.*, cp.shortname, t.name AS toname, f.name AS fromname
                          FROM clarification c
                          LEFT JOIN problem p USING (probid)
                          LEFT JOIN contestproblem cp USING (probid, cid)
                          LEFT JOIN team t ON (t.teamid = c.recipient)
                          LEFT JOIN team f ON (f.teamid = c.sender)
                          LEFT JOIN team_unread u ON (c.clarid=u.mesgid AND u.teamid = %i)
                          WHERE c.cid = %i AND c.sender IS NULL
                          AND ( c.recipient IS NULL OR c.recipient = %i )
                          ORDER BY c.submittime DESC, c.clarid DESC',
                         $teamid, $cid, $teamid);

echo "<h3 class=\"teamoverview\">Clarifications</h3>\n";

# FIXME: column width and wrapping/shortening of clarification text
if ( $clarifications->count() == 0 ) {
	echo "<p class=\"nodata\">No clarifications.</p>\n\n";
} else {
	putClarificationList($clarifications,$teamid);
}

echo "<h3 class=\"teamoverview\">Clarification Requests</h3>\n";

if ( $requests->count() == 0 ) {
	echo "<p class=\"nodata\">No clarification requests.</p>\n\n";
} else {
	putClarificationList($requests,$teamid);
}

echo addForm('clarification.php','get') .
	"<p>" . addSubmit('request clarification') . "</p>" .
	addEndForm();


echo "</div>\n";

require(LIBWWWDIR . '/footer.php');
