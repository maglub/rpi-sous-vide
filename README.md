# Introduction

2016-05-15, NOTE: This is a work in progress. Wait a few weeks and come back.

# rpi-sous-vide
Raspberry pi based sous vide

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

| What  | Raspberry pi | Notes |
|-------|--------------|-------|
| 18B20 | GPIO4        |       |
| SSR-25DA (1)| | Input: +5V, MPSA05 pin 3 |
| SSR-25DA (1)| | Output: 230V phase, boiler |
| SSR-25DA (2)| | Input: +5V, MPSA05 pin 3 |
| SSR-25DA (2)| | Output: 230V phase, pump |

# Runtime

The system is connected like this:

```

 18B20
   |
+-------+                       +-----+                      +------------+
| input | -> tmp/temperature -> | pid | -> tmp/heaterDuty -> | heaterDuty |
+-------+                       +-----+                      +------------+
                                   ^                               |
                                   |                             Relay
             tmp/setpoint ---------+
```



Start the three programs:

* bin/input (sends output to /var/lib/rpi-sous-vide/tmp/temperature)
* bin/pid   (input from /var/lib/rpi-sous-vide/tmp/{temperature, setpoint}, output to /var/lib/rpi-sous-vide/tmp/heaterDuty)
* bin/heaterDuty (input from /var/lib/rpi-sous-vide/tmp/heaterDuty)

# References

* https://developer-blog.net/wp-content/uploads/2013/09/raspberry-pi-rev2-gpio-pinout.jpg
* http://pdf1.alldatasheet.com/datasheet-pdf/view/228064/ONSEMI/MPSA05.html