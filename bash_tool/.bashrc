# .bashrc

# Source global definitions
if [ -f /etc/bashrc ]; then
	. /etc/bashrc
fi

TERM=xterm

export TERM

export PS1="[\u@\h \W]\$ "

