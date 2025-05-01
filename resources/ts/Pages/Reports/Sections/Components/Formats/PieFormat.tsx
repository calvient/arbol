import {Box} from '@calvient/decal';
import {Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip} from 'recharts';

interface PieFormatProps {
  data: Array<{name: string; value: number}>;
}

const COLORS = ['#2B284D', '#6C6897', '#FFB700', '#FF6100', '#FF0080', '#F60950', '#0FD8F0'];

// eslint-disable-next-line
const renderCustomLabel = (props: any) => {
  const RADIAN = Math.PI / 180;
  const {cx, cy, midAngle, innerRadius, outerRadius, percent, name, value, fill} = props;
  const radius = innerRadius + (outerRadius - innerRadius) * 1.25;
  const x = cx + radius * Math.cos(-midAngle * RADIAN);
  const y = cy + radius * Math.sin(-midAngle * RADIAN);
  const percentage = (percent * 100).toFixed(0);
  const formattedValue =
    typeof value === 'number' && isFinite(value)
      ? value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})
      : value;

  return (
    <text
      x={x}
      y={y}
      fill={fill}
      textAnchor={x > cx ? 'start' : 'end'}
      dominantBaseline='central'
      fontSize={14}
    >
      {`${name} - ${formattedValue} (${percentage}%)`}
    </text>
  );
};

const PieFormat = ({data}: PieFormatProps) => {
  return (
    <Box mt={4} w={'full'} h={'400px'}>
      <ResponsiveContainer width='100%' height='100%'>
        <PieChart width={400} height={400}>
          <Legend layout='vertical' verticalAlign='middle' align='right' />
          <Tooltip />
          <Pie
            data={data}
            dataKey='value'
            cx='40%'
            cy='50%'
            outerRadius={120}
            fill='#8884d8'
            label={renderCustomLabel}
          >
            {data.map((_, index) => (
              <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
            ))}
          </Pie>
        </PieChart>
      </ResponsiveContainer>
    </Box>
  );
};

export default PieFormat;
