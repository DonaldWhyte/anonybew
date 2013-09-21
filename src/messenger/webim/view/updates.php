<?php
/*
 * Copyright 2005-2013 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once("inc_menu.php");
$page['title'] = getlocal("updates.title");
$page['menuid'] = "updates";

function tpl_header() { global $page, $webimroot, $jsver;
?>	
<script type="text/javascript" language="javascript" src="<?php echo $webimroot ?>/js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" language="javascript" src="http://mibew.org/latestWebim.js"></script>
<script type="text/javascript" language="javascript" src="<?php echo $webimroot ?>/js/<?php echo $jsver ?>/update.js"></script>
<?php
}

function tpl_content() { global $page, $webimroot;
?>

<?php echo getlocal("updates.intro") ?>
<br />
<br />
<div>
	<div class="mform"><div class="formtop"><div class="formtopi"></div></div><div class="forminner">
	 	
	<p>
		Anonybew is <strong>not compatible</strong> with other versions of Mibew or Mibew updates
		due to the non-confidential data used by the standard Mibew software. This means
		informarion regarding new versions and updates available for Mibew Messenger is not
		available.
	</p>
	<p>
		For the latest version of Anonybew, please visit
		<a href="https://github.com/DonaldWhyte/anonybew">here</a>.
	</p>

	</div><div class="formbottom"><div class="formbottomi"></div></div></div>
</div>		

<?php 
} /* content */

require_once('inc_main.php');
?>