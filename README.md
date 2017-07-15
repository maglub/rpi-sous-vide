# Disclaimer

I make mistakes, very often. Build this on your own risk.

# Introduction

* 2016-05-15, NOTE: This is a work in progress. Please wait a few weeks and come back.
* 2017-07-08, NOTE: This is still a work in progress. It is slowly getting into a state where normal people can install it and use it.


rpi-sous-vide is a Raspberry pi based PID controller for a home built sous vide machinery, a home built smoker (to smoke meat, fish, etc), or anything else that you need to control depending on the current temperature, and a setpoint temperature that you wish to reach. The basic principle is the same. I even use it to control the solar heating for my pool. You measure the media (water or air), and decide to turn on or off a heating element or a pump. Currently it supports two output relays, to control a heating element and a motor to stir water.

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

# Installation

* Install raspbian on an SD card
* Log into your raspberry pi
* Install git
* Clone this repo to ~pi
* Run the setup.sh script (will download and set up php dependencies)
* Reboot (since setup.sh will add items to /boot/config.txt and /etc/modules.conf)

On the raspberry pi:

```
sudo apt-get update
sudo apt-get -y install git

git clone https://github.com/maglub/rpi-sous-vide.git

cd rpi-sous-vide
./setup.sh

sudo shutdown -r now
```

Start the input, control, and output processes in the web GUI. You can also do it on the command line:

```
bin/wrapper startProcesses
```

Set a target temperature:

```
bin/wrapper setSetpoint 65
```

## Different input/control/output/logging possibilities

The way the system figures out which input/control/output script to use, is set by symbolic links in ./bin, where the default symlinks are set as follows:

```
bin/control -> control-available/pid.enhanced
bin/input -> input-available/input-18b20
bin/output -> output-available/heaterOutput
bin/logging -> logging-available/none
```

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

| Type | Item            | Pcs    | Cost | Where | Link |
|-------|----------------|--------|------|-------|------|
| SOC   | Raspberry Pi 3 |      1 |      |       |      |
| Relay | SSR-25DA       |      1 |      |       |      |
| Thermometer | 18B20    |      1 |      |       |      |
| Resistor | 4.7k        |      2 |      |       |      |
| Transistor| MPSA05     |      1 |      |       |      |


## Wiring it up

* Note: I could not find an SSR-25 DA in the Fritzing library.

<img src=pics/schematics.png >

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

* http://hobbybrauer.de/forum/viewtopic.php?f=58&t=3959
* https://github.com/egiust/SousVideAdaptativeArduino/blob/master/README.md
* https://portfolium.com/entry/arduino-sous-vide-cooker

# License

* MIT - do whatever you want with this.
