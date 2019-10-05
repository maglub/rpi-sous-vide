#!/usr/bin/python3

"""This is the pid controller, translated to python
"""

import time
import os
from sys import argv

if argv[1] == '--info':
        print("PID controller written in Python")
        exit(0);


#=========================================
# Classes
#=========================================

class PID:
  """PID controller
  """

  #def __init__(self, P=30.0, I=10.0, D=500.0):
  #def __init__(self, P=30.0, I=10.0, D=500.0):
  #def __init__(self, P=30.0, I=10.0, D=500.0):
  def __init__(self, P=5.0, I=3.0, D=3.0):

    self.Kp = P
    self.Ki = I
    self.Kd = D
    self.offset_Ki = 0.0

    self.setpoint = 0
    self.output = 0
    self.input = 0
    self.lastInput = 0.0

    self.error = 0.0
    self.lastError = 0.0
    self.lastErrorCount = 0
    self.errSum = 0.0
    self.iTerm = 0.0
    self.iMin = -50.0
    self.iMax = 50.0
    self.outMin = 0.0
    self.outMax = 100.0
    self.dInput = 0.0
    self.kd_dInput = 0.0
    self.integral = 0.0
    self.derivative = 0.0

    self.kp_error = 0.0
    self.ki_integral = 0.0
    self.kd_derivative = 0.0


  def init(self):
    self.outMin = 0.0
    self.outMax = 100.0

  def setSetpoint(self, newSetpoint):
    self.setpoint = newSetpoint

  def setInput(self, newInput):
    self.lastInput = self.input
    self.input = newInput

  def compute(self):

    self.error = self.setpoint - self.input
    self.integral = self.integral + self.error * 6 # * dt

    #--- since this is calculated many times more often than the sampling
    #--- we will need to compensate for it somehow (i.e the sampling is
    #--- happening every 3 seconds, hence we should slow down the
    #--- integral calculation ca 60 times)
    self.derivative =  (self.error - self.lastError) / 6 # / dt
    self.dInput =  (self.error - self.lastError) 

    #--- pid_output sensibilization
    if self.integral > self.iMax:
      self.integral = self.iMax
    elif self.integral < self.iMin:
      self.integral = self.iMin

    #--- compute PID output

    self.kp_error      = self.Kp * self.error
    self.ki_integral   = self.Ki * self.integral
    self.kd_derivative = self.Kd * self.derivative

    #--- pid_output sensibilization
    if self.ki_integral > 100:
      self.ki_integral = 100
    elif self.ki_integral <= 0:
      self.ki_integral = 0

    #output should be between 0 - 100(% of possible output)
#    self.output = kp_error + self.iTerm - self.dInput
    self.output = self.kp_error + self.ki_integral + self.kd_derivative

    #--- pid_output sensibilization
    if self.output > self.outMax:
      self.output = self.outMax
    elif self.output < self.outMin:
      self.output = self.outMin

    #--- update last error very rarely
    self.lastErrorCount += 1


    if self.lastErrorCount >=1:
      self.lastError = self.error
      self.lastErrorCount = 0

  #===============================================
  # getSetpoint => fetches the setpoint  temperature from file
  #===============================================
  def getSetpoint(self):
    file = '/dev/shm/setpoint'
    if os.path.exists(file):
      with open(file) as f:
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
    print("pid_windowCount: %-5s setpoint: %-6.2f input: %-6.2f lastInput: %-6.2f dInput: %-6.2f error: %-6.2f kp: %-5s ki: %-5s kd: %-5s eCount: %-3i integral: %-3.2f kp_error: %-6.2f ki_int: %-6.2f kd_der: %-6.2f ${bgColor_output}output: %-5i${bgColor_default}" % (
             0,
             self.setpoint,
             self.input,
             self.lastInput,        # lastInput
             self.dInput,           # dInput
             self.error,            # error
             self.Kp,               # kp
             self.Ki,               # ki
             self.Kd,               # kd
             self.lastErrorCount,   # lastErrorCount
             self.integral,         # integral
             self.kp_error,         # kp_error
             self.ki_integral,      # ki_integral
             self.kd_derivative,    # kd_derivative
             self.output,    # output
           )
         ) 


#=========================================
# MAIN
#=========================================

sleepTime = 6
#pid = PID()
#pid = PID(10,1,5)
pid = PID(50,1.5,50)
pid.init()

while True:
  pid.getInput()
  pid.getSetpoint()
  pid.compute()
  pid.print()
  pid.setOutput()
  time.sleep(sleepTime)

