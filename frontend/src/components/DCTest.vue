<template>
  <div>
    <el-row :gutter="0">
      <el-col :span="11">
        <div id="skill-name-chart" />
      </el-col>
      <el-col :span="13">
        <el-row>
          <el-col :span="12">
            <div id="critical-chart" />
          </el-col>
          <el-col :span="12">
            <div id="skill-action-chart" />
          </el-col>
        </el-row>
        <el-row>
          <div class="dc-data-count">
            <el-col :span="12" :offset="6">
              <span class="filter-count"></span>
              selected out of
              <span class="total-count"></span> records.
            </el-col>
          </div>
        </el-row>
        <el-row>
          <div id="data-table" class="table table-hover" />
        </el-row>
      </el-col>
    </el-row>
  </div>
</template>

<style>
.dc-data-count {
  float: none;
}
</style>

<script>
import { scaleLinear } from "d3-scale";
import "dc/dc.min.css";
import "bootstrap/dist/css/bootstrap.min.css";
import dc from "dc";
import crossfilter from "crossfilter2";
import dcTestData from "../assets/dc-test-data.json";

dcTestData
  .filter(a => a.skillName.trim())
  .forEach(d => {
    d.dateTime = new Date(d.dateTime);
    d.date = d.dateTime.toLocaleDateString();
  });

export default {
  mounted() {
    const criticalChart = dc.pieChart("#critical-chart");
    const skillActionChart = dc.pieChart("#skill-action-chart");
    const skillNameChart = dc.rowChart("#skill-name-chart");
    const logsCount = dc.dataCount(".dc-data-count");
    const logsTable = dc.dataTable("#data-table");

    const ndx = crossfilter(dcTestData);
    const all = ndx.groupAll();

    const dateDimension = ndx.dimension(d => d.dateTime);
    const skillActionDimension = ndx.dimension(d => d.skillAction);
    const skillActionGroup = skillActionDimension.group();
    const criticalDimension = ndx.dimension(d => d.skillCritical);
    const criticalGroup = criticalDimension.group();

    const skillNameDimension = ndx.dimension(d => d.skillName);
    const skillNameGroup = skillNameDimension.group();

    logsCount.crossfilter(ndx).groupAll(all);

    logsTable
      .dimension(dateDimension)
      .size(15)
      .columns([
        "date",
        "skillAction",
        "skillName",
        "skillBy",
        "skillTarget",
        "skillAmount",
        "skillCritical"
      ]);

    const hitPointsPerSkill = skillNameDimension
      .group()
      .reduce(
        (p, v) => p + v.skillAmount,
        (p, v) => p - v.skillAmount,
        () => 0
      );

    skillNameChart
      .cap(25)
      .height((a, b) => {
        const count = skillNameGroup.size() + 1;
        return (count > 25 ? 25 : count) * 40;
      })
      .dimension(skillNameDimension)
      .group(hitPointsPerSkill)
      .elasticX(true);

    skillActionChart
      .width(180)
      .height(180)
      .radius(90)
      .dimension(skillActionDimension)
      .group(skillActionGroup)
      .label(d => d.key)
      .renderLabel(true)
      .innerRadius(30)
      .transitionDuration(500);

    criticalChart
      .width(180)
      .height(180)
      .radius(90)
      .dimension(criticalDimension)
      .group(criticalGroup)
      .label(d => (d.key ? "Critical" : "Uncritical"))
      .renderLabel(true)
      .innerRadius(30)
      .transitionDuration(500);

    dc.renderAll();
  }
};
</script>