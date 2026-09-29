<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * This file contains configuration parameters for the remote storage connectors.
 *
 * This is used by the CLI scripts we use to develop our connectors. Rename this file to config.php and fill in the
 * connection details before running any of the development scripts.
 */

########################################################################################################################
# Download ID
########################################################################################################################
define('ENGINE_DEV_DLID', '');

########################################################################################################################
# BackBlaze B2 configuration
########################################################################################################################
define('ENGINE_DEV_BACKBLAZE_ID', '');
define('ENGINE_DEV_BACKBLAZE_KEY', '');
define('ENGINE_DEV_BACKBLAZE_BUCKET', '');

########################################################################################################################
# Google Storage configuration
########################################################################################################################
#
# IMPORTANT! Put the googlestorage.json file in this directory as well
#
define('ENGINE_DEV_GOOGLE_STORAGE_BUCKET', '');
define('ENGINE_DEV_GOOGLE_STORAGE_PATH', '');

########################################################################################################################
# Windows Azure BLOB Storage
########################################################################################################################
# Necessary preparations for LOCAL development
# ================================================================================
#
# Run local Azure dev server with Docker:
#   docker run -p 10000:10000 -p 10001:10001 mcr.microsoft.com/azure-storage/azurite
# Install Azure CLI (https://docs.microsoft.com/en-us/cli/azure/install-azure-cli?view=azure-cli-latest)
# Create a container called `enginedev`
#   export AZURE_STORAGE_CONNECTION_STRING="DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;"
#   az storage container create -n enginedev
#
# The account and key are hardcoded in Azurite, see https://github.com/azure/azurite
define('ENGINE_DEV_AZURE_BLOB_ACCOUNT', 'devstoreaccount1');
define('ENGINE_DEV_AZURE_BLOB_KEY', 'Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==');
define('ENGINE_DEV_AZURE_BLOB_CONTAINER', 'enginedev');

########################################################################################################################
# OneDrive for Business
########################################################################################################################
# Necessary preparations
# ================================================================================
# Visit https://www.akeeba.com/oauth2/onedrivebusiness.php?callback=foobar to get the tokens
define('ENGINE_DEV_ONEDRIVE_BUSINESS_TOKEN', '');
define('ENGINE_DEV_ONEDRIVE_BUSINESS_REFRESH', '');
define('ENGINE_DEV_ONEDRIVE_BUSINESS_DRIVEID', '');

########################################################################################################################
# OneDrive App-Specific Folder
########################################################################################################################
# Necessary preparations
# ================================================================================
# Visit https://www.akeeba.com/oauth2/onedriveapp.php?callback=foobar to get the tokens
define('ENGINE_DEV_ONEDRIVE_APP_TOKEN', '');
define('ENGINE_DEV_ONEDRIVE_APP_REFRESH', '');

########################################################################################################################
# pCloud Configuration
########################################################################################################################
#
# Visit https://www.akeeba.com/oauth2/pcloud.php?callback=foobar&dlid=YOUR_DOWNLOAD_ID to retrieve this
#
define('ENGINE_DEV_PCLOUD_ACCESS_TOKEN', '');

########################################################################################################################
# Dropbox Configuration
########################################################################################################################
#
# Visit https://www.akeeba.com/oauth2/dropbox.php?callback=foobar to retrieve this
#
define('ENGINE_DEV_DROPBOX_ACCESS_TOKEN', '');
define('ENGINE_DEV_DROPBOX_REFRESH_TOKEN', '');

########################################################################################################################
# OVH Configuration
########################################################################################################################
define('ENGINE_DEV_OVH_PROJECTID', 'abcdef0123456789abcdef0123456789');
define('ENGINE_DEV_OVH_USERNAME', 'user-AbCdEfGhIj01');
define('ENGINE_DEV_OVH_PASSWORD', 'AbCdEfGhIj012345KlMnOpQrStUv6789');
define('ENGINE_DEV_OVH_CONTAINERURL', 'https://storage.de.cloud.ovh.net/v1/AUTH_12345abcdef6789012345abcdef67890/my_container');
define('ENGINE_DEV_OVH_DIRECTORY', '/test');

########################################################################################################################
# Google Drive
########################################################################################################################
define('ENGINE_DEV_GDRIVE_ACCESS_TOKEN', '');
define('ENGINE_DEV_GDRIVE_REFRESH_TOKEN', '');
define('ENGINE_DEV_GDRIVE_FOLDER', '');

########################################################################################################################
# WebDAV
########################################################################################################################
define('ENGINE_DEV_WEBDAV_ENDPOINT', '');
define('ENGINE_DEV_WEBDAV_USERNAME', '');
define('ENGINE_DEV_WEBDAV_PASSWORD', '');
define('ENGINE_DEV_WEBDAV_DIRECTORY', '');

########################################################################################################################
# FTP
########################################################################################################################
define('ENGINE_DEV_FTP_SERVER', 'ftp.example.com');
define('ENGINE_DEV_FTP_PORT', '21');
define('ENGINE_DEV_FTP_PASV', true);
define('ENGINE_DEV_FTP_USERNAME', 'foo');
define('ENGINE_DEV_FTP_PASSWORD', 'bar');
define('ENGINE_DEV_FTP_INITIAL', '/test');
define('ENGINE_DEV_FTP_DIRECTORY', '');
define('ENGINE_DEV_FTP_SSL', false);
define('ENGINE_DEV_FTP_TIMEOUT', 10);
