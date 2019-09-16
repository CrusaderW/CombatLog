export const ACTION_TYPES = {
  HIT: "HIT",
  HEAL: "HEAL",
  DRAIN_DODGE: "DRAIN_DODGE"
};

export const ACTION_TYPES_PARSING = {
  [ACTION_TYPES.HIT]: " hit ",
  [ACTION_TYPES.HEAL]: " healed ",
  [ACTION_TYPES.DRAIN_DODGE]: " drained "
};

export const SKILL_BY_ME = "Your";
export const SKILL_TARGET_ME = " You ";
export const FOR_SPLITTER = " for ";
export const EVENT_SPLITTER = "Event=[";
export const CRITICAL_SUBSTRING = "(Critical)";
