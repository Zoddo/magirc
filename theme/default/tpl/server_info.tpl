<h1>{t 1=$target}Server info for %1{/t}</h1>

<div class="halfleft">
	<table class="details">
		<tr><th>{t}Description{/t}:</th><td><span id="srv_description" class="val"></span></td></tr>
        {if $cfg->service eq 'denora'}<tr><th>{t}Country{/t}:</th><td><span id="srv_country" class="val"></span></td></tr>{/if}
		<tr><th>{t}Online{/t}:</th><td><span id="srv_online" class="val"></span></td></tr>
        {if $cfg->service eq 'denora'}<tr><th>{t}Version{/t}:</th><td><span id="srv_version" class="val"></span></td></tr>
        <tr><th>{t}Uptime{/t}:</th><td><span id="srv_uptime" class="val"></span></td></tr>{/if}
		<tr><th>{t}Last split{/t}:</th><td><span id="srv_lastsplit" class="val"></span></td></tr>
	</table>
</div>
<div class="halfright">
	<table class="details">
        {if $cfg->service eq 'denora'}<tr><th>{t}Last ping{/t}:</th><td><span id="srv_ping" class="val"></span></td></tr>
		<tr><th>{t}Highest ping{/t}:</th><td><span id="srv_maxping" class="val"></span> {t}on{/t} <span id="srv_maxpingtime"></td></tr>{/if}
		<tr><th>{t}Current users{/t}:</th><td><span id="srv_users" class="val"></span></td></tr>
		<tr><th>{t}Max users{/t}:</th><td><span id="srv_maxusers" class="val"></span> {t}on{/t} <span id="srv_maxusertime"></td></tr>
		<tr><th>{t}Current opers{/t}:</th><td><span id="srv_opers" class="val"></span></td></tr>
        {if $cfg->service eq 'denora'}<tr><th>{t}Max opers{/t}:</th><td><span id="srv_maxopers" class="val"></span> {t}on{/t} <span id="srv_maxopertime"></td></tr>{/if}
	</table>
</div>

<div class="clear">&nbsp;</div>

{if $cfg->service eq 'denora'}<h2>{t}Message of the day{/t}</h2>
<div id="srv_motd" class="motd clear" title="MOTD"><pre id="srv_motd_txt"></pre></div>{/if}

{jsmin}
<script type="text/javascript">
{literal}
$(document).ready(function() {
    if (refresh_interval > 0) {
        setInterval(loadContent, refresh_interval);
    }
    function loadContent() {
        $.getJSON("rest/service.php/servers/" + target, function (data) {
            if (data) {
                $("#dialog-server").dialog("option", "title", data.server);
                $("#srv_description").text(data.description);
                $("#srv_country").html(getCountryFlag(data) + ' ' + data.country);
                $("#srv_online").text(data.online ? mLang.Yes : mLang.No);
                $("#srv_version").text(data.version);
                $("#srv_uptime").text(getTimeElapsed(data.uptime));
                if ((data.split_time).indexOf("1970") >= 0) {
                    $("#srv_lastsplit").text(mLang.Never);
                }
                else {
                    $("#srv_lastsplit").text($.format.date(data.split_time, format_datetime));
                }
                $("#srv_ping").text(data.ping);
                $("#srv_maxping").text(data.ping_max);
                $("#srv_maxpingtime").text($.format.date(data.ping_max_time, format_datetime));
                $("#srv_users").text(data.users);
                $("#srv_maxusers").text(data.users_max);
                $("#srv_maxusertime").text($.format.date(data.users_max_time, format_datetime));
                $("#srv_opers").text(data.opers);
                $("#srv_maxopers").text(data.opers_max);
                if (data.opers_max_time != null && (data.opers_max_time).indexOf("1970") < 0) {
                    $("#srv_maxopertime").text(mLang.On + " " + $.format.date(data.opers_max_time, format_datetime));
                }
                $("#srv_motd_txt").html(data.motd ? data.motd_html : mLang.NoMotd);
                $("#dialog-server").dialog("open");
                $("#srv_motd").scrollTop(0);
            } else {
                alert(mLang.Failed);
            }
        }, "json");
    }
    loadContent();
});
{/literal}
</script>
{/jsmin}
