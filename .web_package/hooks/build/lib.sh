#!/bin/bash

[[ -d lib ]] || mkdir lib
cd lib && grab documentation --lang=php -f
