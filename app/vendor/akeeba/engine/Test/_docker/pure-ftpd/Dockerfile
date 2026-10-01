# Akeeba Engine
#
# @package   akeebaengine
# @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
# @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3, or later
#
# Minimal, throw-away pure-ftpd server used by the FTP post-processing engine integration tests
# (Test/Integration/Postproc/FtpTest.php and FtpcurlTest.php). It is NOT shipped with the engine.
#
# alpine:3.20 is a genuine multi-architecture image (linux/amd64 AND linux/arm64), so this container
# runs natively on Apple Silicon without Rosetta/QEMU emulation, and the alpine `pure-ftpd` package
# ships both the server (/usr/sbin/pure-ftpd) and the pure-pw/pure-pw-mkdb virtual-user tools.

FROM alpine:3.20

RUN apk add --no-cache pure-ftpd

COPY entrypoint.sh /usr/local/bin/ftp-entrypoint.sh

RUN chmod +x /usr/local/bin/ftp-entrypoint.sh \
  && mkdir -p /etc/pure-ftpd/passwd /run/pure-ftpd /home/ftpusers

EXPOSE 21

ENTRYPOINT ["/usr/local/bin/ftp-entrypoint.sh"]
