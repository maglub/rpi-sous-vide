#!/usr/bin/python3

"""This is the pid controller, translated to python
"""

import time
import os

#=========================================
# Classes
#=========================================

class PID:
  """PID controller
  """

  def __init__(self, P=30.0, I=10.0, D=500.0):

    self.Kp = P
    self.Ki = I
    self.Kd = D
    self.offset_Ki = 3.0

    self.setpoint = 0
    self.output = 0
    self.input = 0

    self.error = 0.0
    self.errSum = 0.0
    self.iTerm = 0.0
    self.outMin = 0.0
    self.outMax = 100.0
    self.dInput = 0.0
    self.lastInput = 0.0
    self.kd_dInput = 0.0



  def init(self):
    self.error = 0.0
    self.errSum = 0.0
    self.iTerm = 0.0
    self.outMin = 0.0
    self.outMax = 100.0
    self.dInput = 0.0
    self.lastInput = 0.0

  def setSetpoint(self, newSetpoint):
    self.setpoint = newSetpoint

  def setInput(self, newInput):
    self.lastInput = self.input
    self.input = newInput

  def compute(self):

    self.error = self.setpoint - self.input
    self.errSum = self.errSum + self.error

    self.iTerm = self.iTerm + self.Ki * (self.error - self.offset_Ki)

    #--- iTerm
    if ( self.iTerm > self.outMax):
      self.iTerm = self.outMax
    elif self.iTerm < self.outMin:
      self.iTerm = self.outMin

    #--- dInput
    self.dInput= self.input - self.lastInput

    #--- compute PID output

    kp_error = self.Kp * self.error
    self.kd_dInput = self.Kd * self.dInput

    if self.dInput <= self.outMin:
      self.dInput = self.outMin

    self.output = kp_error + self.iTerm - self.dInput

    #--- pid_output sensibilization
    if self.output > self.outMax:
      self.output = self.outMax
    elif self.output < self.outMin:
      self.output = self.outMin

  #===============================================
  # getSetpoint => fetches the setpoint  temperature from file
  #===============================================
  def getSetpoint(self):
    with open('/dev/shm/setpoint') as f:
      try:
        self.setpoint = float(next(f))
      except:
        print("Whoa! Could not read the setpoint file")

  #===============================================
  # getSetpoint => fetches the current target temperature from file
  #===============================================
  def getInput(self):
    file = '/dev/shm/temperature'
    if os.path.exists(file):
      with open(file) as f:
        try:
          self.lastInput = self.input
          self.input = float(next(f))
        except:
          print("Whoa! Could not read the input file")

  #===============================================
  # setOutput => sets the duty cycle in % to the file heaterDuty
  #===============================================
  def setOutput(self):
    outputFile = open('/dev/shm/heaterDuty', 'w')
    outputFile.write(str(int(self.output)))
    outputFile.close()

  def print(self):
    print("pid_windowCount: %-5s setpoint: %-6.2f input: %-6.2f lastInput: %-6.2f dInput: %-6.2f error: %-6.2f kp: %-5s ki: %-5s kd: %-5s omin: %-5s omax: %-5s kp_error: %-6.2f iTerm: %-6.2f kd_dInput: %-6.2f ${bgColor_output}output: %-5i${bgColor_default}" % (
             0,
             self.setpoint,
             self.input,
             self.lastInput, # lastInput
             self.dInput,    # dInput
             self.error,     # error
             self.Kp,        # kp
             self.Ki,        # ki
             self.Kd,        # kd
             0,              # omin
             0,              # omax
             self.Kp * self.error,     # kp_error
             self.iTerm,     # iTerm
             self.kd_dInput, # kd_dInput
             self.output,    # output
           )
         ) 


#=========================================
# MAIN
#=========================================

pid = PID()
pid.init()
pid.setSetpoint(40)

while True:
  pid.getInput()
  pid.getSetpoint()
  pid.compute()
  pid.print()
  pid.setOutput()
  time.sleep(0.1)

