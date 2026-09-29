/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba === "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Backup === "undefined")
{
    akeeba.Backup = {
        URLs:          {},
        tag:           "",
        backupid:      null,
        currentDomain: null,
        domains:       {},
        srpInfo:       {},
        returnUrl:     "",
        returnForm:    "",
        timeoutTimer:  null,
        resumeTimer:   null,
        resume:        {
            retry:        0,
            showWarnings: 0
        }
    };
}

/**
 * Start the timer which launches the next backup step. This allows us to prevent deep nesting of AJAX calls which could
 * lead to performance issues on long backups.
 *
 * @param   waitTime  How much time to wait before starting a backup step, in msec (default: 10)
 */
akeeba.Backup.timer = function (waitTime)
{
    if (waitTime <= 0)
    {
        waitTime = 10;
    }

    setTimeout(akeeba.Backup.timerTick, waitTime);
};

/**
 * This is used by the timer() method to run the next backup step
 */
akeeba.Backup.timerTick = function ()
{
    try
    {
        console.log("Timer tick");
    }
    catch (e)
    {
    }

    // Reset the timer
    var maxExecutionTime = akeeba.System.getOptions("akeeba.Backup.maxExecutionTime", 14);
    var runtimeBias      = akeeba.System.getOptions("akeeba.Backup.runtimeBias", 75);

    akeeba.Backup.resetTimeoutBar();
    akeeba.Backup.startTimeoutBar(maxExecutionTime, runtimeBias);

    // Run the step
    akeeba.System.doAjax({
        ajax: "step", tag: akeeba.Backup.tag, backupid: akeeba.Backup.backupid
    }, akeeba.Backup.onStep, akeeba.Backup.onError, false);
};

/**
 * Starts the timer for the last response timer
 *
 * @param   max_allowance  Maximum time allowance in seconds
 * @param   bias           Runtime bias in %
 */
akeeba.Backup.startTimeoutBar = function (max_allowance, bias)
{
    var lastResponseSeconds = 0;

    akeeba.Backup.timeoutTimer = setInterval(function ()
    {
        lastResponseSeconds++;

        var responseTimer = document.querySelector("#response-timer div.text");
        if (responseTimer)
        {
            responseTimer.textContent = akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_LASTRESPONSE").replace(
                "%s", lastResponseSeconds.toFixed(0));
        }
    }, 1000);
};

/**
 * Resets the last response timer bar
 */
akeeba.Backup.resetTimeoutBar = function ()
{
    try
    {
        clearInterval(akeeba.Backup.timeoutTimer);
    }
    catch (e)
    {
    }

    var responseTimer = document.querySelector("#response-timer div.text");
    if (responseTimer)
    {
        responseTimer.textContent = akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_LASTRESPONSE").replace("%s", "0");
    }
};

/**
 * Starts the timer for the last response timer
 */
akeeba.Backup.startRetryTimeoutBar = function ()
{
    var remainingSeconds = akeeba.System.getOptions("akeeba.Backup.resume.timeout", 10);

    akeeba.Backup.resumeTimer = setInterval(function ()
    {
        remainingSeconds--;
        document.getElementById(
            "akeeba-retry-timeout").textContent = remainingSeconds.toFixed(0);

        if (remainingSeconds == 0)
        {
            clearInterval(akeeba.Backup.resumeTimer);
            akeeba.Backup.resumeBackup();
        }
    }, 1000);
};

/**
 * Resets the last response timer bar
 */
akeeba.Backup.resetRetryTimeoutBar = function ()
{
    clearInterval(akeeba.Backup.resumeTimer);

    var resumeTimeout = akeeba.System.getOptions("akeeba.Backup.resume.timeout", 10);

    document.getElementById("akeeba-retry-timeout").textContent = resumeTimeout.toFixed(0);
};

/**
 * Renders the list of the backup steps
 *
 * @param   active_step  Which is the active step?
 */
akeeba.Backup.renderBackupSteps = function (active_step)
{
    var normal_class = "akeeba-label--green";

    if (active_step == "")
    {
        normal_class = "akeeba-label--grey";
    }

    document.getElementById("backup-steps").innerHTML = "";

    var domains = akeeba.System.getOptions("akeeba.Backup.domains", {});

    for (var counter = 0; counter < domains.length; counter++)
    {
        var element = domains[counter];

        var step       = document.createElement("div");
        step.className = " ";
        step.innerHTML = element[1];
        akeeba.System.data.set("domain", element[0]);
        document.getElementById("backup-steps").appendChild(step);

        if (element[0] == active_step)
        {
            normal_class   = "akeeba-label--grey";
            var this_class = "akeeba-label--teal";
        }
        else
        {
            var this_class = normal_class;
        }

        step.className += " " + this_class;
    }
};

/**
 * Start the backup
 */
akeeba.Backup.start = function ()
{
    try
    {
        console.log("Starting backup");
        console.log(data);
    }
    catch (e)
    {
    }

    // Check for AVG Link Scanner
    if (window.AVGRUN)
    {
        try
        {
            console.warn("AVG Antivirus with Link Checker detected. The backup WILL fail!");
        }
        catch (e)
        {
        }


        var r = confirm(akeeba.System.Text._("SOLO_BACKUP_AVGWARNING"));

        if (!r)
        {
            return;
        }
    }

    // Save the editor contents
    try
    {
        if (akeeba.Backup.commentEditorSave != null)
        {
            akeeba.Backup.commentEditorSave();
        }
    }
    catch (err)
    {
        // If the editor failed to save its content, just move on and ignore the error
        document.getElementById("comment").value = "";
    }

    // Hide the backup setup
    document.getElementById("backup-setup").style.display         = "none";
    // Show the backup progress
    document.getElementById("backup-progress-pane").style.display = "block";

    // Let's check if we have a password even if we didn't set it in the profile (maybe a password manager filled it?)
    var hasAngieKey = akeeba.System.getOptions("akeeba.Backup.hasAngieKey", false);

    if (hasAngieKey)
    {
        document.getElementById("angie-password-warning").style.display = "block";
    }

    // Show desktop notification
    var rightNow = new Date();
    akeeba.System.notification.notify(
        akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_BACKUPSTARTED") + " " + rightNow.toLocaleString());

    // Initialize steps
    akeeba.Backup.renderBackupSteps("");

    // Start the response timer
    var maxExecutionTime = akeeba.System.getOptions("akeeba.Backup.maxExecutionTime", 14);
    var runtimeBias      = akeeba.System.getOptions("akeeba.Backup.runtimeBias", 75);

    akeeba.Backup.startTimeoutBar(maxExecutionTime, runtimeBias);

    // Perform Ajax request
    akeeba.Backup.tag = akeeba.Backup.srpInfo.tag;

    var ajax_request = {
        // Data to send to AJAX
        "ajax":      "start",
        description: document.getElementById("backup-description").value,
        comment:     document.getElementById("comment").value
    };

    ajax_request = array_merge(ajax_request, akeeba.Backup.srpInfo);

    akeeba.System.doAjax(ajax_request, akeeba.Backup.onStep, akeeba.Backup.onError, false);
};

/**
 * Backup step callback handler
 *
 * @param   data  Backup data received
 */
akeeba.Backup.onStep = function (data)
{
    try
    {
        console.log("Running backup step");
        console.log(data);
    }
    catch (e)
    {
    }

    // Update visual step progress from active domain data
    akeeba.Backup.renderBackupSteps(data.Domain);
    akeeba.Backup.currentDomain = data.Domain;

    // Update percentage display
    document.querySelector("#backup-percentage div.bar").style.width = data.Progress + "%";

    // Update step/substep display
    document.getElementById("backup-step").textContent    = data.Step;
    document.getElementById("backup-substep").textContent = data.Substep;

    // Do we have warnings?
    data.Warnings = data.Warnings || [];

    if (data.Warnings.length > 0)
    {
        var barClass = document.getElementById("backup-percentage").className;

        if (barClass.indexOf("akeeba-progress--warning") == -1)
        {
            document.getElementById("backup-percentage").className = "akeeba-progress--warning";
        }

        for (var i = 0; i < data.Warnings.length; i++)
        {
            var warning = data.Warnings[i];

            akeeba.System.notification.notify(akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_BACKUPWARNING"), warning);

            var newDiv         = document.createElement("div");
            newDiv.textContent = warning;
            document.getElementById("warnings-list").appendChild(newDiv);
        }

        document.getElementById("backup-warnings-panel").style.display = "block";
    }

    // Do we have errors?
    var error_message = data.Error;

    if (error_message != "")
    {
        try
        {
            console.error("Got an error message");
            console.log(error_message);
        }
        catch (e)
        {
        }

        // Uh-oh! An error has occurred.
        akeeba.Backup.onError(error_message);

        return;
    }

    // No errors. Good! Are we finished yet?
    if (data["HasRun"] == 1)
    {
        try
        {
            console.log("Backup complete");
            console.log(error_message);
        }
        catch (e)
        {
        }

        // Yes. Show backup completion page.
        akeeba.Backup.onDone();

        return;
    }

    // No. Set the backup tag
    if (empty(akeeba.Backup.tag))
    {
        akeeba.Backup.tag = "backend";
    }

    // Set the backup id
    akeeba.Backup.backupid = data.backupid;

    // Reset the retries
    akeeba.Backup.resume.retry = 0;

    // How much time do I have to wait?
    var waitTime = 10;

    if (data.hasOwnProperty("sleepTime"))
    {
        waitTime = data.sleepTime;
    }

    // ...and send an AJAX command
    try
    {
        console.log("Starting tick timer with waitTime = " + waitTime + " msec");
    }
    catch (e)
    {
    }

    akeeba.Backup.timer(waitTime);
};

/**
 * Resume a backup attempt after an AJAX error has occurred.
 */
akeeba.Backup.resumeBackup = function ()
{
    // Make sure the timer is stopped
    akeeba.Backup.resetRetryTimeoutBar();

    // Hide error and retry panels
    document.getElementById("error-panel").style.display = "none";
    document.getElementById("retry-panel").style.display = "none";

    // Show progress and warnings
    document.getElementById("backup-progress-pane").style.display = "block";

    if (akeeba.Backup.resume.showWarnings)
    {
        document.getElementById("backup-warnings-panel").style.display = "block";
    }

    var rightNow = new Date();
    akeeba.System.notification.notify(
        akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_BACKUPRESUME") + " " + rightNow.toLocaleString());

    // Restart the backup
    akeeba.Backup.timer();
};

/**
 * Cancel the automatic resumption of a backup attempt after an AJAX error has occurred
 */
akeeba.Backup.cancelResume = function ()
{
    // Make sure the timer is stopped
    akeeba.Backup.resetRetryTimeoutBar();

    // Kill the backup
    var errorMessage = document.getElementById("backup-error-message-retry").innerHTML;
    akeeba.Backup.endWithError(errorMessage);
};

/**
 * AJAX error callback
 *
 * @param   message  The error message received
 */
akeeba.Backup.onError = function (message)
{
    // If resume is not enabled, die.
    if (!akeeba.System.getOptions("akeeba.Backup.resume.enabled", true))
    {
        akeeba.Backup.endWithError(message);

        return;
    }

    // If we are past the max retries, die.
    var maxRetries = akeeba.System.getOptions("akeeba.Backup.resume.maxRetries");
    if (akeeba.Backup.resume.retry >= maxRetries)
    {
        akeeba.Backup.endWithError(message);

        return;
    }

    // Make sure the timer is stopped
    akeeba.Backup.resume.retry++;
    akeeba.Backup.resetRetryTimeoutBar();

    var resumeNotificationMessage         = akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_BACKUPHALT_DESC");
    var resumeTimeout                     = akeeba.System.getOptions("akeeba.Backup.resume.timeout", 10);
    var resumeNotificationMessageReplaced = resumeNotificationMessage.replace(
        "%d", resumeTimeout.toFixed(0));
    akeeba.System.notification.notify(
        akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_BACKUPHALT"), resumeNotificationMessageReplaced);

    // Save display state of warnings panel
    akeeba.Backup.resume.showWarnings = (document.getElementById("backup-warnings-panel").style.display !== "none");

    // Hide progress and warnings
    document.getElementById("backup-progress-pane").style.display  = "none";
    document.getElementById("backup-warnings-panel").style.display = "none";
    document.getElementById("error-panel").style.display           = "none";

    // Setup and show the retry pane
    document.getElementById("backup-error-message-retry").textContent = message;
    document.getElementById("retry-panel").style.display              = "block";

    // Start the countdown
    akeeba.Backup.startRetryTimeoutBar();
};

/**
 * Terminate the backup with an error
 *
 * @param   message  The error message received
 */
akeeba.Backup.endWithError = function (message)
{
    // Make sure the timer is stopped
    akeeba.Backup.resetTimeoutBar();

    var alice_autorun = false;

    // Hide progress and warnings
    document.getElementById("backup-progress-pane").style.display  = "none";
    document.getElementById("backup-warnings-panel").style.display = "none";
    document.getElementById("retry-panel").style.display           = "none";

    // Set up the view log URL
    var logURL     = akeeba.System.getOptions("akeeba.Backup.URLs.LogURL");
    var aliceURL   = akeeba.System.getOptions("akeeba.Backup.URLs.AliceURL");
    var viewLogUrl = logURL + "&tag=" + akeeba.Backup.tag;
    var aliceUrl   = aliceURL + "&log=" + akeeba.Backup.tag;

    if (akeeba.Backup.backupid)
    {
        viewLogUrl = viewLogUrl + "." + encodeURIComponent(akeeba.Backup.backupid);
        aliceUrl   = aliceUrl + "." + encodeURIComponent(akeeba.Backup.backupid);
    }

    if (akeeba.Backup.currentDomain == "finale")
    {
        alice_autorun = true;
        aliceUrl += "&autorun=1";
    }

    document.getElementById("ab-viewlog-error").setAttribute("href", viewLogUrl);
    document.getElementById("ab-alice-error").setAttribute("href", aliceUrl);

    akeeba.System.notification.notify(akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_BACKUPFAILED"), message);

    // Try to send a push notification for failed backups
    akeeba.System.doAjax({
        "ajax":         "pushFail",
        "tag":          akeeba.Backup.tag,
        "backupid":     akeeba.Backup.backupid,
        "errorMessage": message
    }, function (msg)
    {
    });

    // Setup and show error pane
    document.getElementById("backup-error-message").textContent = message;
    document.getElementById("error-panel").style.display        = "block";

    // Do we have to automatically analyze the log?
    if (alice_autorun)
    {
        setTimeout(function ()
        {
            window.location = aliceUrl;
        }, 500);
    }
};

/**
 * Backup finished callback handler
 */
akeeba.Backup.onDone = function ()
{
    var rightNow = new Date();
    akeeba.System.notification.notify(
        akeeba.System.Text._("COM_AKEEBA_BACKUP_TEXT_BACKUPFINISHED") + " " + rightNow.toLocaleString());

    // Make sure the timer is stopped
    akeeba.Backup.resetTimeoutBar();

    // Hide progress
    document.getElementById("backup-progress-pane").style.display = "none";

    // Show finished pane
    document.getElementById("backup-complete").style.display     = "block";
    document.getElementById("backup-warnings-panel").style.width = "100%";

    // Show correct log URL
    var logURL     = akeeba.System.getOptions("akeeba.Backup.URLs.LogURL");
    var viewLogUrl = logURL + "&tag=" + akeeba.Backup.tag;

    // If the backup completes in a single pageload the backup tag and backupid are not returned. So I need to cheat.
    if (!akeeba.Backup.tag)
    {
        viewLogUrl = logURL + "&latest=1";
    }
    else if (akeeba.Backup.backupid)
    {
        viewLogUrl = viewLogUrl + "." + encodeURIComponent(akeeba.Backup.backupid);
    }

    try
    {
        document.getElementById("ab-viewlog-success").setAttribute("href", viewLogUrl);
    }
    catch (e)
    {
    }

    // Proceed to the return URL if it is set, using a POST redirect or a "standard" one

    if (akeeba.System.getOptions("akeeba.Backup.returnForm", false))
    {
        document.getElementById("returnForm").submit();

        return;
    }

    var returnUrl = akeeba.System.getOptions("akeeba.Backup.returnUrl", "");

    if (returnUrl !== "")
    {
        window.location = returnUrl;
    }
};

akeeba.Backup.restoreDefaultOptions = function ()
{
    document.getElementById("backup-description").value = akeeba.System.getOptions("akeeba.Backup.defaultDescription");

    document.getElementById("comment").value = "ThisIsADummyStringToWorkAroundChrome";
    document.getElementById("comment").value = "";
};

akeeba.Backup.flipProfile = function ()
{
    // Save the description and comments
    document.getElementById("flipDescription").value = document.getElementById("backup-description").value;
    document.getElementById("flipComment").value     = document.getElementById("comment").value;
    document.forms.profileForm.submit();
};

akeeba.System.documentReady(function ()
{
    akeeba.System.addEventListener("backup-start", "click", function (e)
    {
        e.preventDefault();
        akeeba.Backup.start();

        return false;
    });

    akeeba.System.addEventListener("backup-default", "click", function (e)
    {
        e.preventDefault();
        akeeba.Backup.restoreDefaultOptions();

        return false;
    });

    akeeba.System.addEventListener("comAkeebaBackupFlipProfile", "click", function (e)
    {
        e.preventDefault();
        akeeba.Backup.flipProfile();

        return false;
    });

    akeeba.System.addEventListener("comAkeebaBackupResumeCancel", "click", function (e)
    {
        e.preventDefault();
        akeeba.Backup.cancelResume();

        return false;
    });

    akeeba.System.addEventListener("comAkeebaBackupResume", "click", function (e)
    {
        e.preventDefault();
        akeeba.Backup.resumeBackup();

        return false;
    });

    akeeba.System.addEventListener("profileId", "change", akeeba.Backup.flipProfile);

    setTimeout(function ()
    {
        if (akeeba.System.getOptions("akeeba.Backup.autoStart", false))
        {
            akeeba.Backup.start();
        }
    }, 500);

    akeeba.System.notification.askPermission();
});