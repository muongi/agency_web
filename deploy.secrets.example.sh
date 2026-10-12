# Copy this file to deploy.secrets.sh and set muongi's remote sudo password.
# deploy.secrets.sh is gitignored — never commit it.
#
#   cp deploy.secrets.example.sh deploy.secrets.sh
#   chmod 600 deploy.secrets.sh
#
# Use single quotes if the password contains $ or other shell metacharacters.

export REMOTE_SUDO_PASSWORD='your-muongi-sudo-password-here'
