<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;

use Akeeba\Engine\Factory;
use Akeeba\UsageStats\Collector\Constants\SoftwareType;
use Akeeba\UsageStats\Collector\StatsCollector;
use Awf\Mvc\Model;
use Awf\Uri\Uri;
use Solo\Helper\HashHelper;

class Stats extends Model
{
    /**
     * Send site information to the remove collection service
     *
     * @param  bool  $useIframe  Should I use an IFRAME?
     *
     * @return bool
     */
    public function collectStatistics($useIframe)
    {
	    // Is data collection turned off?
        if (!$this->container->appConfig->get('stats_enabled', 1))
        {
            return false;
        }

	    // Make sure the autoloader for our Composer dependencies is loaded.
	    if (!class_exists(StatsCollector::class))
	    {
		    try
		    {
			    require_once APATH_BASE . '/vendor/autoload.php';
		    }
		    catch (\Throwable $e)
		    {
			    return false;
		    }
	    }

		// Usage stats collection class is undefined, we cannot continue
	    if (!class_exists(StatsCollector::class, false))
	    {
		    return false;
	    }

		// Make sure we have a version
		if (!defined('AKEEBABACKUP_VERSION'))
		{
			try
			{
				require_once APATH_BASE . '/version.php';
			}
			catch (\Throwable $e)
			{
				if (!defined('AKEEBABACKUP_VERSION'))
				{
					define('AKEEBABACKUP_VERSION', 'dev');
					define('AKEEBABACKUP_DATE', date('Y-m-d'));
				}
			}
		}

	    try
	    {
		    (new StatsCollector(
			    SoftwareType::AB_WP_CORE,
			    AKEEBABACKUP_VERSION,
			    defined('AKEEBABACKUP_PRO') ? AKEEBABACKUP_PRO : false
		    ))->conditionalSendStatistics();
	    }
	    catch (\Throwable $e)
	    {
		    return false;
	    }

		return true;
    }
}
