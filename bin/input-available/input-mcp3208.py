#!/usr/bin/python

import spidev
import time
import os
import sys

import argparse
parser = argparse.ArgumentParser(prog='myprogram')
parser.add_argument('--input', help='input port')
args = parser.parse_args()

#print("input : {} ".format(args.input))

# Open SPI bus
spi = spidev.SpiDev()
spi.open(0,0)
spi.max_speed_hz=1000000
 
# Function to read SPI data from MCP3008 chip
# Channel must be an integer 0-7
def ReadChannel(channel):
  adc = spi.xfer2([1,(8+channel)<<4,0])
  data = ((adc[1]&3) << 8) + adc[2]
  return data
 
# Function to convert data to voltage level,
# rounded to specified number of decimal places.
def ConvertVolts(data,places):
  volts = (data * 3.3) / float(1023)
  volts = round(volts,places)
  return volts
 
# Function to calculate temperature from
# TMP36 data, rounded to specified
# number of decimal places.
def ConvertTemp(data,places):
 
  # ADC Value
  # (approx)  Temp  Volts    Ohm
  #  856        5    3.0     375k
  #  789       19.5  (2.55)
  #  756       24    2.74    200k 
  #  454       62    1.57    41.8k  
  #  260       92   
  #  234       95   
  #  207       98    0.674   11.9k
 
  dData = float(207 - 856)
  dTemp = float(93)
  offset = float(98 + 29) + 0.5
  
  temp = float ( (dTemp / dData) * data) + offset

  temp = round(temp,places)
  return temp
 
# Define sensor channels
#light_channel = 0
temp_channel  = int(args.input)
 
# Define delay between readings
delay = 3
 
#while True:
temp_level = ReadChannel(temp_channel)
temp_volts = ConvertVolts(temp_level,2)
temp       = ConvertTemp(temp_level,2)
 
#print("Input: {}, Temp : {} ({}V) {} deg C".format(args.input, temp_level,temp_volts,temp))
sys.stderr.write("Input: {}, Temp : {} ({}V) {} deg C \n".format(args.input, temp_level,temp_volts,temp))
print("{}".format(temp))
 
# Wait before repeating loop
#time.sleep(delay)

