# Introduction
Raspberry pi based sous vide

2016-05-15, NOTE: This is a work in progress. Please wait a few weeks and come back.


This was a "one day project" that turned into a weekend project, that turned into... It is in no state to be used by anyone else than me at the moment, but the basic idea is to have a web-gui based on:


````
lighttpd -> php -> php slim
````

The web gui is useful for monitoring and setting constants and setpoints.

One of my design criterias is to separate the problem domain on the low level to three "daemons":

* input (temperature)
* control (temperature control, i.e PID or other algorithm)
* output heater control (setting the heater to a range of 0% to 100%)

The future system is thought to be illustrated like this:

```
          +------------------------------+   +----------+   +----------------------+
          |          web gui             |   | rest api |   | LCD/Display, buttons |
          +------------------------------+   +----------+   +----------------------+
                                      |            |            |
                                  +--------------------------------+
 tmp/{temperature, heaterDuty} -> |              app api           | -> tmp/setpoint
                                  +--------------------------------+
		                                          ^
		  +---------+                             |
          |  logger | -------> db/{temperature, heaterDuty, ...}.rrd
		  +---------+
                ^
                |
                +-------------------------------------+
                     |                                |
  18B20              |                                |
    |                |                                |
+-------+            |          +---------+           |          +--------+
| input | -> tmp/temperature -> | control | -> tmp/heaterDuty -> | output |
+-------+                       +---------+                      +--------+
             tmp/setpoint -----------^                               |
                                                                   Relay ---> Boiler/Heater
```

Separating the components in that way, I can play around with different ways of implementing the different components. I.e:

* The input could be changed to use PT1000 sensors through an AD converter/SPI and a different piece of code
* The control can be the default PID code, or be could be any algorithm of choice
* The output could use the default "slow" PWM or other means of control that can accept a range from 0 - 100 as input.

# Installation

* Clone this repo to your linux/Mac (no idea what to do for Windows users)
* Install raspbian on an SD card
* There is some ansible scripts here, that can be used to set up the raspberry pi.
* Log into your raspberry pi
* Clone this repo to ~pi
* Run the setup.sh script (will download and set up php dependencies)

```
sudo apt-get update
sudo apt-get -y install git

git clone https://github.com/maglub/rpi-sous-vide.git

cd rpi-sous-vide
./setup.sh

sudo shutdown -r now
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

| Type | Item            | Number | Cost | Where | Link |
|-------|----------------|--------|------|-------|------|
| SOC   | Raspberry Pi 3 |      1 |      |       |      |
| Relay | SSR-25DA       |      2 |      |       |      |
| Thermometer | 18B20    |      1 |      |       |      |
| Resistor | 4.7k        |      3 |      |       |      |
| Transistor| MPSA05     |      2 |      |       |      |
| Aquarium pump |
| Old water boiler |


# Hardware

| What       | Raspberry pi | Notes |
|------------|--------------|-------|
| 18B20      | GPIO4        |       |
| MPSA05 (1) | base -> 4.7R -> GPIO17       | |
| MPSA05 (2) | base -> 4.7R -> GPIO27       |
| SSR-25DA (1)| | Input: +5V, MPSA05 pin 3 |
| SSR-25DA (1)| | Output: 230V phase, boiler |
| SSR-25DA (2)| | Input: +5V, MPSA05 pin 3 |
| SSR-25DA (2)| | Output: 230V phase, pump |

# Runtime




Start the three programs:

| Program | Description | Input | Output |
|---------|-------------|-------|--------|
| bin/input   | Symlinked to the input of choice in input-available. | Thermometer. 18B20 | /var/lib/rpi-sous-vide/tmp/temperature                                             |
| bin/control | Symlinked to the control program of choice in control-available. | /var/lib/rpi-sous-vide/tmp/{temperature, setpoint} | /var/lib/rpi-sous-vide/tmp/heaterDuty  |
| bin/output  | Symlinked to the output program of choice in output-available.   | /var/lib/rpi-sous-vide/tmp/heaterDuty              | Solid state relay over GPIO            |

# References

* Main inspiration: https://learn.adafruit.com/sous-vide-powered-by-arduino-the-sous-viduino/pid
* Arduino PID library: http://brettbeauregard.com/blog/2011/04/improving-the-beginners-pid-introduction/

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
