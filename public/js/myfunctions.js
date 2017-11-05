//var firstTime = true;

function updateStats(){

  var url="/api/all";
  d3.json(url, function(data){
      document.getElementById("temperature").innerHTML = "Temperature: " + data["temperature"];
      document.getElementById("setpoint").innerHTML = "Setpoint: " + data["setpoint"];
      document.getElementById("heaterDuty").innerHTML = "HeaterDuty: " + data["heaterDuty"];
      //drawBar(data);
      drawArc(data);
  });

  setTimeout('updateStats()', 5000);
}

function setupArc(){

//  var canvas = d3.select("#gauge").append("svg").attr("width",1000).attr("height",50).attr("id", "bar");
  // ugly centering of the gauge on an iphone
  var canvas = d3.select("#gauge").append("svg").attr("width",600).attr("height",200).append("g").attr("transform", "translate(145,100)").attr("id", "arc");

}

function drawBar(d){

  var data = [100, d["setpoint"], d["temperature"] ];
  var color = d3.scale.ordinal().range(["red", "blue", "orange"]);

  var canvas = d3.select("#bar");

  canvas.selectAll("rect").remove();

  var bars = canvas.selectAll("rect").data(data).enter()
               .append("rect")
               .attr("width", function(d) {return d * 10;})
               .attr("height", function(d,i){ return 50 - 10*i; } )
               .attr("y", function(d, i) { return 5*i; })
               .attr("fill", function(d, i) { return color(i);})
               .attr("id", function(d,i){ return "niklas"+i;});
}

function drawArc(d){

  //#--- we only need a bit of data from the json
  var data = [100, d["setpoint"], d["temperature"] ];
  var color = d3.scale.ordinal().range(["#ddd", "#d00", "#0d0"]);
  var arcScale = d3.scale.linear()
                   .domain([0,100])
                   .range([0,2*Math.PI]);

  var canvas = d3.select("#arc");
  canvas.selectAll("arc").remove();
  canvas.selectAll("path").remove();

  log.log("debug!");

  var arc = d3.svg.arc()
    .innerRadius(50)
    .outerRadius(100)
    .startAngle(0);

  canvas.append("path").datum({endAngle: arcScale(100)})
    .style("fill", color(0))
    .attr("d", arc)
    .attr("id", "path-background");

  canvas.append("path").datum({endAngle: arcScale(d["setpoint"]) })
    .style("fill", "#d00")
    .attr("d", arc)
    .attr("id", "path-setpoint");

  arc.innerRadius(55);
  arc.outerRadius(95);
  canvas.append("path").datum({endAngle: arcScale(d["temperature"]) })
    .style("fill", "#0d0")
    .attr("d", arc)
    .attr("id", "path-temperature");

  canvas.selectAll("text").remove();
  var addText = canvas.selectAll("text").data([ d["temperature"], d["setpoint"] ]).enter().append("text");
  var textElements = addText
      .attr("x", function(d,i){ return 0; })
      .attr("y", function(d,i){ return 15-i*20; })
      .text(function(d,i){ log.log(d); return d;})
      .attr("text-anchor", "middle")
      .attr("font-family", "Arial Black")
      .attr("font-size", "20px")
      .attr("fill", "black");

//  log.log("Temperature: " + d["temperature"] + " Setpoint: " + d["setpoint"] );
  return 0;
}

