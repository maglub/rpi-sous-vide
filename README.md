# Disclaimer

I make mistakes, very often. Build this on your own risk.

# Introduction

* 2018-05-04, Migration from Slim 2 to Slim 3 framework done
* 2017-10-25, Added installation scripts for influxdb and grafana

NOTE NOTE NOTE: If you do a "git pull" 2018-05-04 or later, on a system that has been installed before, you will have to run "./setup.sh" again, to resolve the new dependencies of the migration from the Slim2 to Slim3 php framework.

```
#--- upgrade from Slim2 to Slim3
cd ~/rpi-sous-vide
rm -rf vendor
./setup.sh
```

rpi-sous-vide is a Raspberry pi based PID controller for a home built sous vide machinery, a home built smoker (to smoke meat, fish, etc), a greenhouse, or anything else that you need to control depending on the current temperature, and a setpoint temperature that you wish to reach. The basic principle is the same. I even use it to control the solar heating for my pool. You measure the media (water or air), and decide to turn on or off a heating element or a pump. Currently it supports two output relays, to control a heating element and a motor to stir water.

The basic idea is to:

* Control the temperature in a setting
* Web based application
* Metric/Temperature collection and graphing

The application, as seen on a mobile device:

<img width=250px src=pics/screenshot-mobilephone.png>

Dasboard in grafana:

<img src=pics/screenshot-bacon.png>

This was a "one day project" that turned into a weekend project, that turned into... It is not really in a state to be used by anyone else than me at the moment, but the basic idea is to have a web-gui based on:


````
lighttpd -> php -> php slim
````

The web gui is useful for monitoring and setting constants and setpoints.

One of my design criterias is to separate the problem domain on the low level to three "daemons":

* input (temperature) => Gets input from your hardware (i.e ds18s20 or MAX6675), writes to tmp/temperature
* control (temperature control, i.e PID or other algorithm) => reads from tmp/temperature and tmp/setpoint, writes to tmp/heaterDuty 
* output heater control (setting the heater to a range of 0% to 100%) reads from tmp/heaterDuty, controls the relay(s)

Conceptual view:

```
      +------------------------------+   +----------+   +----------------------+
      |          web gui             |   | rest api |   | LCD/Display, buttons |
      +------------------------------+   +----------+   +----------------------+
                                  |            |            |
                              +--------------------------------+
                              |              app api           | 
                              |          (bin/functions)       | 
                              +--------+-----------------------+
	                               |           ^
           +---------+                 |           |
           |  logger |-> i.e influx    |           | 
           +---------+                 |           |
                ^                      |           |
                |                      |           |
                +----+-----------------)----+------+--+
                     |                 |    |         |
                     |                 V    |         |
                     |              tmp/setpoint      |
  18B20              |                 |              |
    |                |                 V              |
+-------+            |          +---------+           |          +--------+
| input | -> tmp/temperature -> | control | -> tmp/heaterDuty -> | output |
+-------+                       +---------+                      +--------+
                                                                      |
                                                                      V
                                                                   Relay ---> Boiler/Heater/Pump
```

Separating the components in that way, I can play around with different ways of implementing the different components. I.e:

* The input could potentially be changed to use PT1000 sensors or k-type sensors through an AD converter/SPI and a different piece of code. The only thing you need to do, is to write a looping script that reads the temperature(s) from your device, and writes it to the tmp/temperature file.
* The control can be the default PID code, or be could be any algorithm of choice
* The output could use the default "slow" PWM or other means of control that can accept a range from 0 - 100 as input.

# Compatibility

This matrix show which versions of raspbian that works. Please try and use the "newest" possible, as I am not testing backwards compatibility properly.

| Raspbian Distribution | lite | GUI | PHP |
| ------------------------- |:--:|:--:|:--:|
| Stretch | :white_check_mark: | :white_check_mark: | 7.0 |
| Jessie | :white_check_mark:| :no_entry:| 5.6 |
| Older | :grey_question: | :grey_question: | :grey_question: |
| Reaally old | :no_entry: | :no_entry: | :no_entry: |


# Installation

* Install raspbian on an SD card
* Log into your raspberry pi
* Install git
* Clone this repo to ~pi
* Run the setup.sh script (will download and set up php dependencies)
* Run the setup-influddb.sh script (will download and set up influxdb)
* Run the setup-grafana.sh script (will download and set up php dependencies)
* Reboot (since setup.sh will add items to /boot/config.txt and /etc/modules.conf)
* Go to http://YOUR_PI_IP for the web gui
* Go to http://YOUR_PI_IP:3000 for grafana (if installed)

On the raspberry pi:

```
sudo apt-get update
sudo apt-get -y install git

git clone https://github.com/maglub/rpi-sous-vide.git

cd rpi-sous-vide

#--- if you need en_US.UTF-8
# piLoggerLocale=true ./setup.sh

./setup.sh
./setup-influxdb.sh
./setup-grafana.sh

sudo shutdown -r now
```

After the reboot, the 1wire interface is enabled and you should be able to see your devices. You should give your sensors names in the conf/aliases.conf file now.

```
#--- if you set up a greenhouse, change the application_type to "greenhouse" in conf/app.conf
cd rpi-sous-vide
./bin/genAliases | tee conf/aliases.conf
```

Start the input, control, and output processes in the web GUI. You can also do it on the command line:

```
bin/wrapper startProcesses
```

Set a target temperature:

```
bin/wrapper setSetpoint 65
```

## Install influxdb and grafana

Note, the installation of influxdb and grafana will take a while. I only show the installation for Raspberry Pi 3 (or newer), since it does not make sense to install it on the older ones.

* Influxdb

```
./setup-influxdb.sh
```

* Grafana

```
./setup-grafana.sh
```

## Upgrade

* On the command line

```
git pull
./setup.sh
```

* You can also do a git pull from the GUI, which does not automatically run the ./setup.sh script

## Different input/control/output/logging possibilities

The way the system figures out which input/control/output script to use, is set by symbolic links in ./bin, where the default symlinks are set as follows:

```
bin/control -> control-available/pid.enhanced
bin/input -> input-available/input-18b20
bin/output -> output-available/heaterOutput
bin/logging-enabled/none -> logging-available/none
```

For control, input, and output you can only link to one script. For logging, any symlink in bin/logging-enabled will be executed, hence you can log to multiple destinations if you like.

You can copy these scripts and adapt them in any way you like. In particular the input script might need different setup on your Raspberry Pi, and the setup script will try and satisfy this by calling the input script with the "--setup" parameter.

```
bin/input --setup
```

So if you have some esoteric hardware that need special configuration (like the 1wire sensors), you can put that in your input file in the doSetup() function.

## Influxdb logging

If you have an influxdb server available, you can redirect the logging to it and create a new database for it. Update the influxdb variables in conf/app.conf, and symlink the logging script. In the influxdb script, there is an exampla curl call to create the database. There are three values logged at the moment:

* control: smoker-output-pwm
* input: smoker-setpoint
* input: smoker-temp

```
cd bin
ln -sf logging-available/influxdb logging

ls -l logging
lrwxrwxrwx 1 pi pi 26 Jul  8 19:35 logging -> logging-available/influxdb
```

Influxdb examples:

* Create database "smoker" on server 192.168.4.55

```
curl -G http://192.168.4.55:8086/query --data-urlencode "q=CREATE DATABASE smoker"
```

* Query all values from table "sensor_data" in the database "smoker" server 192.168.4.55

```
curl -G 'http://192.168.4.55:8086/query?pretty=true' --data-urlencode "db=smoker" --data-urlencode "q=SELECT * FROM sensor_data"
```

# Usage

Most things are hidden in the "functions" file. There is a wrapper that can be used to list the functions, and control them from outside the scripts.

```
bin/wrapper --list
```

* Set the target (setpoint) temperature:

```
bin/wrapper setSetpoint 65
```

* Turn all off (using an alias)

````
svBin=/home/pi/rpi-sous-vide/bin
alias off='$svBin/wrapper setHeaterDuty 0 ; $svBin/wrapper setSetpoint 0 ; $svBin/wrapper setHeater off; $svBin/wrapper setPump off'

off
````

# BOM (bill of materials)

| Type           | Item                                  | Pcs    | Cost      | Where       | Link |
|----------------|---------------------------------------|--------|-----------|-------------|------|
| SOC            | Raspberry Pi 3                        |      1 | 34.29 chf |             | http://ch.farnell.com/raspberry-pi/raspberrypi3-modb-1gb/sbc-raspberry-pi-3-model-b-1gb/dp/2525225    |
| SOC            | Raspberry Pi 3 case                   |      1 | 7.00 chf  |             | https://ch.farnell.com/raspberry-pi/raspberry-pi3-case/geh-use-raspberry-pi-3-model-b/dp/2519567 |
| SOC            | (alternative) Raspberry Pi Zero W     |    (1) | 32.94 EUR |             | https://buyzero.de/collections/boards-kits/products/copy-of-raspberry-pi-zero-w?variant=31485043154   |
| SD card        | SD Card, 16GB (more than enough)      |      1 | 16.00 chf | Digitec.ch  |      |
| PSU            | USB charger, 2.5A                     |      1 | 7.99 Eur  |             | https://buyzero.de/collections/raspberry-pi-zubehor/products/netzteil-2-5-a    |
| Relay          | SSR-25DA                              |      1 | $2.70     |             | https://www.aliexpress.com/item/1-pcs-SSR-25DA-25A-3-32V-DC-TO-24-380V-AC-SSR-25DA-relay-solid/32712665772.html   |
| Rail clip      | SSR-25DA                              |      2 | $2.32     |             | https://www.aliexpress.com/item/single-phase-SSR-35MM-DIN-rail-fixed-solid-state-relay-clip-clamp-with-2-mounting-screws/32679765326.html | 
| Thermometer    | 18B20, with 3m cable                  |      1 | $1.50     | Ali Express |      |
| Resistor       | 4.7k                                  |      2 | 0.10 chf  |             |      |
| Transistor     | MPSA05 or MPSA06 NPN                  |      1 | 0.10 chf  |             |      |
| Box            | IP55, F-Tronic, including DIN rail    |      1 | 35.00 chf |  Bauhaus    |      |
| Connector      | Dupont crimp connectors, 1 pin, 4 pin |      6 | 0.10 chf  |             |      | 
| Connector      | WAGO  compact splicing connector      |      1 | 0.5 chf   |             | https://www.aliexpress.com/item/221-413-Original-WAGO-connector-led-splice-connector-COMPACT-Splicing-Connectors-3-conductor-connector-100-Original/32681506587.html |
| Cable          | 20AWG silicon wire                    |     1m | 0.50 chf  |             | https://www.aliexpress.com/item/UL-3135-20AWG-high-temperature-Silicone-wire-3135-20-silica-gel-wires-Conductor-construction-100-0/32260276471.html |

Ca cost for a Raspberry Pi 3 based system:

* Subtotal CHF: 95.00 chf
* Subtotal EUR: 7.99 EUR => ca 9.00 chf
* Subtotal USD: $6.52 => 6.50 chf

Total (ca) in CHF: ca 110.00 chf


## Wiring it up

* Note: I could not find an SSR-25 DA in the Fritzing library.

<img src=pics/schematics.png >

* Wiring up the solid state control

<img src=pics/relay-control-soldered.jpg >

* Wiring up the ds18b20 (if you have only one thermometer)

<img src=pics/ds18b20-soldered.jpg >

* The breakout board (if you have more than one thermometer)

<img src=pics/one_wire_breakout.jpg>

* The breakout board cable 

<img src=pics/one_wire_breakout_cable.jpg>

* The thermometers connected to the breakout board

<img src=pics/one_wire_cables_connected.jpg>


# Runtime

Start the three programs:

| Program | Description | Input | Output |
|---------|-------------|-------|--------|
| bin/input   | Symlinked to the input of choice in input-available. | Thermometer. 18B20 | /var/lib/rpi-sous-vide/tmp/temperature                                             |
| bin/control | Symlinked to the control program of choice in control-available. | /var/lib/rpi-sous-vide/tmp/{temperature, setpoint} | /var/lib/rpi-sous-vide/tmp/heaterDuty  |
| bin/output  | Symlinked to the output program of choice in output-available.   | /var/lib/rpi-sous-vide/tmp/heaterDuty              | Solid state relay over GPIO            |

# Notes

## DS18s20

* One sample take ca 0.9 seconds on a Raspberry Pi Zero, and 0.8 seconds on a Raspberry Pi 3B, so don't expect this to work in very fast changing environments. 

# Feedback/bugs/feature requests/Contributions

Please provide any bug reports, feature requests or other feedback in the Issue tracker here in this project. I happily receive contributions!

* https://github.com/maglub/rpi-sous-vide/issues

If you would like to contribute code, the process is simple.

* Please create an issue with a feature request
* If you have code you would like to contribute, please fork this project and devlop your enhancement.
* When you are ready, please create a pull request

# References

* Main inspiration: https://learn.adafruit.com/sous-vide-powered-by-arduino-the-sous-viduino/pid
* Arduino PID library: http://brettbeauregard.com/blog/2011/04/improving-the-beginners-pid-introduction/

* Adafruit tutorial for 1wire 18b20: https://learn.adafruit.com/adafruits-raspberry-pi-lesson-11-ds18b20-temperature-sensing/hardware
* DS18b20 datasheet: https://datasheets.maximintegrated.com/en/ds/DS18B20.pdf 

* https://socomponents.co.uk/raspberry-pi-ds18b20-temperature-sensing/
* http://www.reuk.co.uk/wordpress/raspberry-pi/ds18b20-temperature-sensor-with-raspberry-pi/

* https://developer-blog.net/wp-content/uploads/2013/09/raspberry-pi-rev2-gpio-pinout.jpg
* http://pdf1.alldatasheet.com/datasheet-pdf/view/228064/ONSEMI/MPSA05.html

* Solid state relay: http://www.fotek.com.hk/solid/SSR-1.htm
* http://www.omega.com/prodinfo/temperaturecontrollers.html
* http://www.instructables.com/id/Raspberry-Pi-Sous-Vide/?ALLSTEPS
* https://github.com/drewhavard/rasp-sous-vide/blob/master/get_temp.pl
* Heat sinking the solid state relay: http://www.scienceprog.com/considering-solid-state-relays-ssr-for-your-projects/

## Misc

* https://www.circuits.dk/install-grafana-influxdb-raspberry/
* http://hobbybrauer.de/forum/viewtopic.php?f=58&t=3959
* https://github.com/egiust/SousVideAdaptativeArduino/blob/master/README.md
* https://portfolium.com/entry/arduino-sous-vide-cooker

Now you will be able to browse your new dashboard on:

* http://yourPi-IP:3000/dashboard/db/smoker?refresh=10s&orgId=1

# License

* MIT - do whatever you want with this.
